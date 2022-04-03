<?php
namespace TYPO3Fluid\Fluid\Core\Cache;

/*
 * This file belongs to the package "TYPO3 Fluid".
 * See LICENSE.txt that was shipped with this package.
 */

use TYPO3Fluid\Fluid\Core\Compiler\FailedCompilingState;
use TYPO3Fluid\Fluid\Core\Compiler\StopCompilingException;
use TYPO3Fluid\Fluid\Core\Parser\ParsedTemplateInterface;
use TYPO3Fluid\Fluid\Core\Parser\SyntaxTree\Expression\ExpressionException;
use TYPO3Fluid\Fluid\Core\Parser\TemplateParser;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\View\Exception;
use TYPO3Fluid\Fluid\View\TemplatePaths;

/**
 * Class StandardCacheWarmer
 *
 * Responsible for performing a full warmup process.
 * Receives just the RenderingContext (which can be custom for the
 * framework that invokes the warmup) and resolves all possible
 * template files in all supported formats and triggers compiling
 * of those templates.
 *
 * The compiling process can be supported in detail in templates
 * directly through using the `f:cache.*` collection of ViewHelpers.
 * The compiler is put into a special warmup mode which can in turn
 * be checked by ViewHelpers when compiling which allows third-party
 * ViewHelpers to more closely control how they are compiled, if
 * they are at all compilable.
 *
 * The result of the warmup process is returned as a
 * FluidCacheWarmupResult instance with reports for every template
 * file that was detected duringthe process; detailing whether or
 * not the template file was compiled, some metadata about the
 * template such as which Layout it uses, if any, and finally adds
 * mitigation suggestions when a template cannot be compiled.
 *
 * The mitigation suggestions are specifically generated by this
 * class and can be elaborated or changed completely by any third-
 * party implementation of FluidCacheWarmerInterface which allows
 * them to be specific to the framework in which Fluid is used.
 * The default set of mitigation suggestions are based on the
 * standard errors which can be thrown by the Fluid engine.
 */
class StandardCacheWarmer implements FluidCacheWarmerInterface
{

    /**
     * Template file formats (file extensions) supported by this
     * cache warmer implementation.
     *
     * @var array
     */
    protected $formats = ['html', 'xml', 'txt', 'json', 'rtf', 'atom', 'rss'];

    /**
     * Warm up an entire collection of templates based on the
     * provided RenderingContext (the TemplatePaths carried by
     * the RenderingContext, to be precise).
     *
     * Returns a FluidCacheWarmupResult with result information
     * about all detected template files and the compiling of
     * those files. If a template fails to compile or throws an
     * error, a mitigation suggestion is included for that file.
     *
     * @param RenderingContextInterface $renderingContext
     * @return FluidCacheWarmupResult
     */
    public function warm(RenderingContextInterface $renderingContext)
    {
        $renderingContext->getTemplateCompiler()->enterWarmupMode();
        $result = new FluidCacheWarmupResult();
        $result->merge(
            $this->warmupTemplateRootPaths($renderingContext),
            $this->warmupPartialRootPaths($renderingContext),
            $this->warmupLayoutRootPaths($renderingContext)
        );
        return $result;
    }

    /**
     * Warm up _templateRootPaths_ of the RenderingContext's
     * TemplatePaths instance.
     *
     * Scans for template files recursively in all template root
     * paths while respecting overlays, e.g. if a path replaces
     * the template file of a lower priority path then only
     * one result is returned - the overlayed template file. In
     * other words the resolving happens exactly as if you were
     * attempting to render each detected controller, so that the
     * compiled template will be the same that is resolved when
     * rendering that controller.
     *
     * Also scans the root level of all templateRootPaths for
     * controller-less/fallback-action template files, e.g. files
     * which would be rendered if a specified controller's action
     * template does not exist (fallback-action) or if no controller
     * name was specified in the context (controller-less).
     *
     * Like other methods, returns a FluidCacheWarmupResult instance
     * which can be merged with other result instances.
     *
     * @param RenderingContextInterface $renderingContext
     * @return FluidCacheWarmupResult
     */
    protected function warmupTemplateRootPaths(RenderingContextInterface $renderingContext)
    {
        $result = new FluidCacheWarmupResult();
        $paths = $renderingContext->getTemplatePaths();
        foreach ($this->formats as $format) {
            $paths->setFormat($format);
            $formatCutoffPoint = - (strlen($format) + 1);
            foreach ($paths->getTemplateRootPaths() as $templateRootPath) {
                $pathCutoffPoint = strlen($templateRootPath);
                foreach ($this->detectControllerNamesInTemplateRootPaths([$templateRootPath]) as $controllerName) {
                    foreach ($paths->resolveAvailableTemplateFiles($controllerName, $format) as $templateFile) {
                        $state = $this->warmSingleFile(
                            $templateFile,
                            $paths->getTemplateIdentifier(
                                $controllerName,
                                substr($templateFile, $pathCutoffPoint, $formatCutoffPoint)
                            ),
                            $renderingContext
                        );
                        $result->add($state, $templateFile);
                    }
                }
                $limitedPaths = clone $paths;
                $limitedPaths->setTemplateRootPaths([$templateRootPath]);
                foreach ($limitedPaths->resolveAvailableTemplateFiles(null, $format) as $templateFile) {
                    $state = $this->warmSingleFile(
                        $templateFile,
                        $paths->getTemplateIdentifier(
                            'Default',
                            substr($templateFile, $pathCutoffPoint, $formatCutoffPoint)
                        ),
                        $renderingContext
                    );
                    $result->add($state, $templateFile);
                }
            }
        }
        return $result;
    }

    /**
     * Warm up _partialRootPaths_ of the provided RenderingContext's
     * TemplatePaths instance. Simple, recursive processing of all
     * supported format template files in path(s), compiling only
     * the topmost (override) template file if the same template
     * exists in multiple partial root paths.
     *
     * Like other methods, returns a FluidCacheWarmupResult instance
     * which can be merged with other result instances.
     *
     * @param RenderingContextInterface $renderingContext
     * @return FluidCacheWarmupResult
     */
    protected function warmupPartialRootPaths(RenderingContextInterface $renderingContext)
    {
        $result = new FluidCacheWarmupResult();
        $paths = $renderingContext->getTemplatePaths();
        foreach ($this->formats as $format) {
            $formatCutoffPoint = - (strlen($format) + 1);
            foreach ($paths->getPartialRootPaths() as $partialRootPath) {
                $limitedPaths = clone $paths;
                $limitedPaths->setPartialRootPaths([$partialRootPath]);
                $pathCutoffPoint = strlen($partialRootPath);
                foreach ($limitedPaths->resolveAvailablePartialFiles($format) as $partialFile) {
                    $paths->setFormat($format);
                    $state = $this->warmSingleFile(
                        $partialFile,
                        $paths->getPartialIdentifier(substr($partialFile, $pathCutoffPoint, $formatCutoffPoint)),
                        $renderingContext
                    );
                    $result->add($state, $partialFile);
                }
            }
        }
        return $result;
    }

    /**
     * Warm up _layoutRootPaths_ of the provided RenderingContext's
     * TemplatePaths instance. Simple, recursive processing of all
     * supported format template files in path(s), compiling only
     * the topmost (override) template file if the same template
     * exists in multiple layout root paths.
     *
     * Like other methods, returns a FluidCacheWarmupResult instance
     * which can be merged with other result instances.
     *
     * @param RenderingContextInterface $renderingContext
     * @return FluidCacheWarmupResult
     */
    protected function warmupLayoutRootPaths(RenderingContextInterface $renderingContext)
    {
        $result = new FluidCacheWarmupResult();
        $paths = $renderingContext->getTemplatePaths();
        foreach ($this->formats as $format) {
            $formatCutoffPoint = - (strlen($format) + 1);
            foreach ($paths->getLayoutRootPaths() as $layoutRootPath) {
                $limitedPaths = clone $paths;
                $limitedPaths->setLayoutRootPaths([$layoutRootPath]);
                $pathCutoffPoint = strlen($layoutRootPath);
                foreach ($limitedPaths->resolveAvailableLayoutFiles($format) as $layoutFile) {
                    $paths->setFormat($format);
                    $state = $this->warmSingleFile(
                        $layoutFile,
                        $paths->getLayoutIdentifier(substr($layoutFile, $pathCutoffPoint, $formatCutoffPoint)),
                        $renderingContext
                    );
                    $result->add($state, $layoutFile);
                }
            }
        }
        return $result;
    }

    /**
     * Detect all available controller names in provided TemplateRootPaths
     * array, returning the "basename" components of controller-template
     * directories encountered, as an array.
     *
     * @param array $templateRootPaths
     * @return \Generator
     */
    protected function detectControllerNamesInTemplateRootPaths(array $templateRootPaths)
    {
        foreach ($templateRootPaths as $templateRootPath) {
            foreach ((array) glob(rtrim($templateRootPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*') as $pathName) {
                if (is_dir($pathName)) {
                    yield basename($pathName);
                }
            }
        }
    }

    /**
     * Warm up a single template file.
     *
     * Performs reading, parsing and attempts compiling of a single
     * template file. Catches errors that may occur and reports them
     * in a FailedCompilingState (which can then be `add()`'ed to
     * the FluidCacheWarmupResult to assimilate the information within.
     *
     * Adds basic mitigation suggestions for each specific type of error,
     * giving hints to developers if a certain template fails to compile.
     *
     * @param string $templatePathAndFilename
     * @param string $identifier
     * @param RenderingContextInterface $renderingContext
     * @return ParsedTemplateInterface
     */
    protected function warmSingleFile($templatePathAndFilename, $identifier, RenderingContextInterface $renderingContext)
    {
        $parsedTemplate = new FailedCompilingState();
        $parsedTemplate->setVariableProvider($renderingContext->getVariableProvider());
        $parsedTemplate->setCompilable(false);
        $parsedTemplate->setIdentifier($identifier);
        try {
            $parsedTemplate = $renderingContext->getTemplateParser()->getOrParseAndStoreTemplate(
                $identifier,
                $this->createClosure($templatePathAndFilename)
            );
        } catch (StopCompilingException $error) {
            $parsedTemplate->setFailureReason(sprintf('Compiling is intentionally disabled. Specific reason unknown. Message: "%s"', $error->getMessage()));
            $parsedTemplate->setMitigations([
                'Can be caused by specific ViewHelpers. If this is is not intentional: avoid ViewHelpers which disable caches.',
                'If cache is intentionally disabled: consider using `f:cache.static` to cause otherwise uncompilable ViewHelpers\' output to be replaced with a static string in compiled templates.'
            ]);
        } catch (ExpressionException $error) {
            $parsedTemplate->setFailureReason(sprintf('ExpressionNode evaluation error: %s', $error->getMessage()));
            $parsedTemplate->setMitigations([
                'Emulate variables used in ExpressionNode using `f:cache.warmup` or assign in warming RenderingContext'
            ]);
        } catch (\TYPO3Fluid\Fluid\Core\Parser\Exception $error) {
            $parsedTemplate->setFailureReason($error->getMessage());
            $parsedTemplate->setMitigations([
                'Fix possible syntax errors.',
                'Check that all ViewHelpers are correctly referenced and namespaces loaded (note: namespaces may be added externally!)',
                'Check that all ExpressionNode types used by the template are loaded (note: may depend on RenderingContext implementation!)',
                'Emulate missing variables used in expressions by using `f:cache.warmup` around your template code.'
            ]);
        } catch (\TYPO3Fluid\Fluid\Core\ViewHelper\Exception $error) {
            $parsedTemplate->setFailureReason(sprintf('ViewHelper threw Exception: %s', $error->getMessage()));
            $parsedTemplate->setMitigations([
                'Emulate missing variables using `f:cache.warmup` around failing ViewHelper.',
                'Emulate globals / context required by ViewHelper.',
                'Disable caching for template if ViewHelper depends on globals / context that cannot be emulated.'
            ]);
        } catch (\TYPO3Fluid\Fluid\Core\Exception $error) {
            $parsedTemplate->setFailureReason(sprintf('Fluid engine error: %s', $error->getMessage()));
            $parsedTemplate->setMitigations([
                'Search online for additional information about specific error.'
            ]);
        } catch (Exception $error) {
            $parsedTemplate->setFailureReason(sprintf('Fluid view error: %s', $error->getMessage()));
            $parsedTemplate->setMitigations([
                'Investigate reported error in View class for missing variable checks, missing configuration etc.',
                'Consider using a different View class for rendering in warmup mode (a custom rendering context can provide it)'
            ]);
        } catch (\RuntimeException $error) {
            $parsedTemplate->setFailureReason(
                sprintf(
                    'General error: %s line %s threw %s (code: %d)',
                    get_class($error),
                    $error->getFile(),
                    $error->getLine(),
                    $error->getMessage(),
                    $error->getCode()
                )
            );
            $parsedTemplate->setMitigations([
                'There are no automated suggestions for mitigating this issue. An online search may yield more information.'
            ]);
        }
        return $parsedTemplate;
    }

    /**
     * @param string $templatePathAndFilename
     * @return \Closure
     */
    protected function createClosure($templatePathAndFilename)
    {
        return function(TemplateParser $parser, TemplatePaths $templatePaths) use ($templatePathAndFilename) {
            return file_get_contents($templatePathAndFilename);
        };
    }
}
