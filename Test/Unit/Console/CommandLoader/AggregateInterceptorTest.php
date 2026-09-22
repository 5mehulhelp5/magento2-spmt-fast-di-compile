<?php

declare(strict_types=1);

namespace Spmt\FastDiCompile\Test\Unit\Console\CommandLoader;

use Magento\Setup\Console\Command\DiCompileCommand;
use PHPUnit\Framework\TestCase;
use Spmt\FastDiCompile\Console\Command\FastDiCompileCommand;
use Spmt\FastDiCompile\Console\CommandLoader\AggregateInterceptor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\CommandLoader\FactoryCommandLoader;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @covers \Spmt\FastDiCompile\Console\CommandLoader\AggregateInterceptor
 */
class AggregateInterceptorTest extends TestCase
{
    private string $testRoot;

    protected function setUp(): void
    {
        $this->testRoot = BP;
        $this->resetTestRoot();
    }

    protected function tearDown(): void
    {
        $this->resetTestRoot();
    }

    public function testReturnsFastCommandWhenPackageCompilerBinaryIsTrusted(): void
    {
        $this->createTrustedPackageCompilerBinary();
        $loader = $this->createLoader();

        $command = $loader->get(DiCompileCommand::NAME);

        $this->assertInstanceOf(FastDiCompileCommand::class, $command);
    }

    public function testReturnsFastCommandWhenDevelopmentCompilerBinaryIsTrusted(): void
    {
        $this->createTrustedDevelopmentCompilerBinary();
        $loader = $this->createLoader();

        $command = $loader->get(DiCompileCommand::NAME);

        $this->assertInstanceOf(FastDiCompileCommand::class, $command);
    }

    public function testFallsBackToStandardCommandWhenCompilerBinaryIsMissing(): void
    {
        $standardCommand = $this->createStandardCommand(DiCompileCommand::NAME);
        $loader = $this->createLoader($standardCommand);

        $command = $loader->get(DiCompileCommand::NAME);

        $this->assertSame($standardCommand, $command);
    }

    public function testFallsBackToStandardCommandWhenPackageCompilerBinaryIsSymlink(): void
    {
        $target = $this->testRoot . '/safe-target';
        file_put_contents($target, "#!/usr/bin/env php\n<?php exit(0);\n");
        chmod($target, 0755);
        $binary = $this->createPackageCompilerBinaryDirectory() . '/fast-di-compile';

        if (!symlink($target, $binary)) {
            $this->markTestSkipped('Symlinks are not available in this environment.');
        }

        $standardCommand = $this->createStandardCommand(DiCompileCommand::NAME);
        $loader = $this->createLoader($standardCommand);

        $command = $loader->get(DiCompileCommand::NAME);

        $this->assertSame($standardCommand, $command);
    }

    public function testFallsBackToStandardCommandWhenPackageCompilerDirectoryIsGroupWritable(): void
    {
        $binaryDirectory = $this->createPackageCompilerBinaryDirectory();
        file_put_contents($binaryDirectory . '/fast-di-compile', "#!/usr/bin/env php\n<?php exit(0);\n");
        chmod($binaryDirectory . '/fast-di-compile', 0755);
        chmod($binaryDirectory, 0775);
        $standardCommand = $this->createStandardCommand(DiCompileCommand::NAME);
        $loader = $this->createLoader($standardCommand);

        $command = $loader->get(DiCompileCommand::NAME);

        $this->assertSame($standardCommand, $command);
    }

    public function testDelegatesOtherCommandsToParentLoader(): void
    {
        $otherCommand = $this->createStandardCommand('cache:clean');
        $loader = $this->createLoader(null, $otherCommand);

        $command = $loader->get('cache:clean');

        $this->assertSame($otherCommand, $command);
    }

    private function createLoader(?Command $diCommand = null, ?Command $otherCommand = null): AggregateInterceptor
    {
        $diCommand ??= $this->createStandardCommand(DiCompileCommand::NAME);
        $otherCommand ??= $this->createStandardCommand('cache:clean');

        return new AggregateInterceptor([
            new FactoryCommandLoader([
                DiCompileCommand::NAME => static fn(): Command => $diCommand,
                'cache:clean' => static fn(): Command => $otherCommand,
            ]),
        ]);
    }

    private function createStandardCommand(string $name): Command
    {
        return new class($name) extends Command {
            public function __construct(string $name)
            {
                parent::__construct($name);
            }

            protected function execute(InputInterface $input, OutputInterface $output): int
            {
                return Command::SUCCESS;
            }
        };
    }

    private function createTrustedPackageCompilerBinary(): string
    {
        return $this->createTrustedCompilerBinary($this->createPackageCompilerBinaryDirectory());
    }

    private function createTrustedDevelopmentCompilerBinary(): string
    {
        return $this->createTrustedCompilerBinary($this->createDevelopmentCompilerBinaryDirectory());
    }

    private function createTrustedCompilerBinary(string $binaryDirectory): string
    {
        $binary = $binaryDirectory . '/fast-di-compile';
        file_put_contents($binary, "#!/usr/bin/env php\n<?php exit(0);\n");
        chmod($binary, 0755);

        return $binary;
    }

    private function createPackageCompilerBinaryDirectory(): string
    {
        return $this->createDirectoryTree([
            $this->testRoot . '/vendor',
            $this->testRoot . '/vendor/spmt',
            $this->testRoot . '/vendor/spmt/magento2-spmt-fast-di-compile',
            $this->testRoot . '/vendor/spmt/magento2-spmt-fast-di-compile/bin',
        ]);
    }

    private function createDevelopmentCompilerBinaryDirectory(): string
    {
        return $this->createDirectoryTree([
            $this->testRoot . '/rust',
            $this->testRoot . '/rust/di-compiler',
            $this->testRoot . '/rust/di-compiler/target',
            $this->testRoot . '/rust/di-compiler/target/release',
        ]);
    }

    /**
     * @param string[] $parts
     */
    private function createDirectoryTree(array $parts): string
    {
        foreach ($parts as $part) {
            if (!is_dir($part)) {
                mkdir($part, 0755, true);
            }
            chmod($part, 0755);
        }

        return end($parts);
    }

    private function resetTestRoot(): void
    {
        if (is_dir($this->testRoot)) {
            $this->removePath($this->testRoot);
        }

        mkdir($this->testRoot, 0755, true);
    }

    private function removePath(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $iterator = new \FilesystemIterator($path, \FilesystemIterator::SKIP_DOTS);
        foreach ($iterator as $item) {
            $this->removePath($item->getPathname());
        }

        rmdir($path);
    }
}
