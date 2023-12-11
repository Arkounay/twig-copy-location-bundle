<?php

namespace Arkounay\Bundle\TwigCopyLocationBundle\DataCollector;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;
use Symfony\Component\HttpKernel\DataCollector\LateDataCollectorInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\VarDumper\Cloner\Data;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Profiler\Profile;

/**
 * Class TwigCollector
 * @package Arkounay\Bundle\TwigCopyLocationBundle\DataCollector
 * Can't extend TwigDataCollector anymore since it's marked as final
 */
class TwigCollector extends DataCollector implements EventSubscriberInterface, LateDataCollectorInterface
{

    private \SplObjectStorage $controllers;

    public function __construct(private readonly Profile $profile, private readonly Environment $twig)
    {
        $this->controllers = new \SplObjectStorage();
    }


    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::CONTROLLER => 'onKernelController'];
    }

    /**
     * {@inheritdoc}
     *
     * @param \Throwable|null $exception
     */
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
    {
        $this->data['controller'] = $this->parseController($this->controllers[$request] ?? null);
    }

    /**
     * {@inheritdoc}
     */
    public function reset(): void
    {
        $this->profile->reset();
        $this->data = [];
    }

    public function onKernelController(ControllerEvent $event): void
    {
        $this->controllers[$event->getRequest()] = $event->getController();
    }

    /**
     * {@inheritdoc}
     */
    public function lateCollect(): void
    {
        $this->data['profile'] = serialize($this->profile);
        $this->data['template_paths'] = [];

        if (null === $this->twig) {
            return;
        }

        $templateFinder = function (Profile $profile) use (&$templateFinder) {
            if ($profile->isTemplate()) {
                try {
                    $template = $this->twig->load($name = $profile->getName());
                } catch (LoaderError $e) {
                    $template = null;
                }

                if (null !== $template && '' !== ($path = $template->getSourceContext()->getPath()) && !str_contains($name, '@WebProfiler')) {
                    $this->data['template_paths'][$name] = $path;
                }
            }

            foreach ($profile as $p) {
                $templateFinder($p);
            }
        };
        $templateFinder($this->profile);
    }

    public function getController(): array|string|Data
    {
        return $this->data['controller'];
    }

    public function getTemplatePaths(): array
    {
        return $this->data['template_paths'];
    }

    /**
     * @return array|string An array of controller data or a simple string
     */
    private function parseController(array|object|string|null $controller): array|string
    {
        if (\is_string($controller) && str_contains($controller, '::')) {
            $controller = explode('::', $controller);
        }

        if (\is_array($controller)) {
            try {
                $r = new \ReflectionMethod($controller[0], $controller[1]);

                return [
                    'class' => \is_object($controller[0]) ? get_debug_type($controller[0]) : $controller[0],
                    'method' => $controller[1],
                    'file' => $r->getFileName(),
                    'line' => $r->getStartLine(),
                ];
            } catch (\ReflectionException) {
                if (\is_callable($controller)) {
                    // using __call or  __callStatic
                    return [
                        'class' => \is_object($controller[0]) ? get_debug_type($controller[0]) : $controller[0],
                        'method' => $controller[1],
                        'file' => 'n/a',
                        'line' => 'n/a',
                    ];
                }
            }
        }

        if ($controller instanceof \Closure) {
            $r = new \ReflectionFunction($controller);

            $controller = [
                'class' => $r->getName(),
                'method' => null,
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            ];

            if (str_contains($r->name, '{closure}')) {
                return $controller;
            }
            $controller['method'] = $r->name;

            if ($class = $r->getClosureScopeClass()) {
                $controller['class'] = $class->name;
            } else {
                return $r->name;
            }

            return $controller;
        }

        if (\is_object($controller)) {
            $r = new \ReflectionClass($controller);

            return [
                'class' => $r->getName(),
                'method' => null,
                'file' => $r->getFileName(),
                'line' => $r->getStartLine(),
            ];
        }

        return \is_string($controller) ? $controller : 'n/a';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'app.twig_collector';
    }

}
