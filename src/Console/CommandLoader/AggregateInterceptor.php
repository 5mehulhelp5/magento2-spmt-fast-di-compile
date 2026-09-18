<?php

declare(strict_types=1);

namespace Spmt\FastDiCompile\Console\CommandLoader;

use Magento\Framework\Console\CommandLoader\Aggregate;
use Magento\Setup\Console\Command\DiCompileCommand;
use SplFileInfo;
use Spmt\FastDiCompile\Console\Command\FastDiCompileCommand;
use Symfony\Component\Console\Command\Command;

/**
 * Intercepts the setup:di:compile command and delegates to the fast-di-compile Rust binary.
 *
 * Falls back to the original PHP compiler (via parent) if the binary is absent.
 */
class AggregateInterceptor extends Aggregate
{
    /**
     * Binary location relative to Magento's project root.
     */
    private const BINARY_RELATIVE_PATH = 'rust/di-compiler/target/release/fast-di-compile';

    /**
     * Permission bits that allow write access outside the file owner.
     */
    private const GROUP_OR_OTHER_WRITE_MASK = 00022;

    /**
     * Return the requested console command.
     *
     * @param string $name
     * @return Command
     */
    public function get(string $name): Command
    {
        if ($name === DiCompileCommand::NAME) {
            $binary = $this->getTrustedBinaryPath();
            if ($binary !== null) {
                return new FastDiCompileCommand($binary, parent::get($name));
            }
        }

        return parent::get($name);
    }

    /**
     * Return the compiler binary path only when it is local, executable, and not loosely writable.
     *
     * @return string|null
     */
    private function getTrustedBinaryPath(): ?string
    {
        $rootInfo = new SplFileInfo(BP);
        $root = $rootInfo->getRealPath();
        if (!is_string($root)) {
            return null;
        }

        $binary = $root . DIRECTORY_SEPARATOR . self::BINARY_RELATIVE_PATH;
        $binaryFile = new SplFileInfo($binary);
        if (!$binaryFile->isFile() || !$binaryFile->isExecutable()) {
            return null;
        }

        if ($this->pathContainsSymlink($binary, $root)) {
            return null;
        }

        $realPath = $binaryFile->getRealPath();
        if (!is_string($realPath) || !$this->isPathInsideRoot($realPath, $root)) {
            return null;
        }

        if (!$this->hasTrustedPermissions($binary, $root)) {
            return null;
        }

        return $realPath;
    }

    /**
     * Check whether a path is inside Magento's project root.
     *
     * @param string $path
     * @param string $root
     * @return bool
     */
    private function isPathInsideRoot(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * Reject symlinked compiler paths so a trusted-looking path cannot jump elsewhere.
     *
     * @param string $path
     * @param string $root
     * @return bool
     */
    private function pathContainsSymlink(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $relativePath = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
        $currentPath = $root;

        foreach (explode(DIRECTORY_SEPARATOR, $relativePath) as $pathPart) {
            if ($pathPart === '') {
                continue;
            }

            $currentPath .= DIRECTORY_SEPARATOR . $pathPart;
            if ((new SplFileInfo($currentPath))->isLink()) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reject compiler binaries and parent directories writable by group or other users.
     *
     * @param string $path
     * @param string $root
     * @return bool
     */
    private function hasTrustedPermissions(string $path, string $root): bool
    {
        $root = rtrim($root, DIRECTORY_SEPARATOR);
        $relativePath = ltrim(substr($path, strlen($root)), DIRECTORY_SEPARATOR);
        $currentPath = $root;

        foreach (explode(DIRECTORY_SEPARATOR, $relativePath) as $pathPart) {
            if ($pathPart === '') {
                continue;
            }

            $currentPath .= DIRECTORY_SEPARATOR . $pathPart;
            $permissions = (new SplFileInfo($currentPath))->getPerms();
            if ($permissions === false || ($permissions & self::GROUP_OR_OTHER_WRITE_MASK) !== 0) {
                return false;
            }
        }

        return true;
    }
}
