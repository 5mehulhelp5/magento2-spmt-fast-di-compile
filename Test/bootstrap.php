<?php

declare(strict_types=1);

namespace {
    $packageRoot = dirname(__DIR__);
    $autoloadFiles = [
        $packageRoot . '/vendor/autoload.php',
        dirname(__DIR__, 3) . '/vendor/autoload.php',
    ];

    foreach ($autoloadFiles as $autoloadFile) {
        if (is_file($autoloadFile)) {
            require_once $autoloadFile;
            break;
        }
    }

    if (!defined('BP')) {
        define('BP', $packageRoot . '/Test/_tmp/bp');
    }

    if (!is_dir(BP)) {
        mkdir(BP, 0755, true);
    }

    spl_autoload_register(static function (string $class) use ($packageRoot): void {
        $prefix = 'Spmt\\FastDiCompile\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $packageRoot . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require_once $file;
        }
    });
}

namespace Magento\Framework\Component {
    if (!class_exists(ComponentRegistrar::class, false)) {
        class ComponentRegistrar
        {
            public const MODULE = 'module';

            /**
             * @var array<int, array{type: string, name: string, path: string}>
             */
            public static array $registered = [];

            /**
             * @param string $type
             * @param string $name
             * @param string $path
             * @return void
             */
            public static function register(string $type, string $name, string $path): void
            {
                self::$registered[] = [
                    'type' => $type,
                    'name' => $name,
                    'path' => $path,
                ];
            }
        }
    }
}

namespace Magento\Framework\Console {
    if (!class_exists(Cli::class, false)) {
        class Cli
        {
            public const RETURN_SUCCESS = 0;
            public const RETURN_FAILURE = 1;
        }
    }
}

namespace Magento\Setup\Console\Command {
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\Input\InputInterface;
    use Symfony\Component\Console\Output\OutputInterface;

    if (!class_exists(DiCompileCommand::class, false)) {
        class DiCompileCommand extends Command
        {
            public const NAME = 'setup:di:compile';

            /**
             * @param InputInterface $input
             * @param OutputInterface $output
             * @return int
             */
            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        }
    }
}

namespace Magento\Framework\Console\CommandLoader {
    use Symfony\Component\Console\Command\Command;
    use Symfony\Component\Console\CommandLoader\CommandLoaderInterface;
    use Symfony\Component\Console\Exception\CommandNotFoundException;

    if (!class_exists(Aggregate::class, false)) {
        class Aggregate implements CommandLoaderInterface
        {
            /**
             * @var CommandLoaderInterface[]
             */
            private array $commandLoaders;

            /**
             * @param CommandLoaderInterface[] $commandLoaders
             */
            public function __construct(array $commandLoaders = [])
            {
                $this->commandLoaders = $commandLoaders;
            }

            /**
             * @param string $name
             * @return Command
             */
            public function get(string $name): Command
            {
                foreach ($this->commandLoaders as $commandLoader) {
                    if ($commandLoader->has($name)) {
                        return $commandLoader->get($name);
                    }
                }

                throw new CommandNotFoundException(sprintf('Command "%s" does not exist.', $name));
            }

            /**
             * @param string $name
             * @return bool
             */
            public function has(string $name): bool
            {
                foreach ($this->commandLoaders as $commandLoader) {
                    if ($commandLoader->has($name)) {
                        return true;
                    }
                }

                return false;
            }

            /**
             * @return string[]
             */
            public function getNames(): array
            {
                $names = [];
                foreach ($this->commandLoaders as $commandLoader) {
                    $names = array_merge($names, $commandLoader->getNames());
                }

                return $names;
            }
        }
    }
}
