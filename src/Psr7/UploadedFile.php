<?php

declare(strict_types=1);

namespace PFinal\AsyncioHttp\Psr7;

use InvalidArgumentException;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use RuntimeException;

/**
 * PSR-7 UploadedFile 实现
 */
class UploadedFile implements UploadedFileInterface
{
    private const ERRORS = [
        UPLOAD_ERR_OK,
        UPLOAD_ERR_INI_SIZE,
        UPLOAD_ERR_FORM_SIZE,
        UPLOAD_ERR_PARTIAL,
        UPLOAD_ERR_NO_FILE,
        UPLOAD_ERR_NO_TMP_DIR,
        UPLOAD_ERR_CANT_WRITE,
        UPLOAD_ERR_EXTENSION,
    ];

    private string|StreamInterface $streamOrFile;
    private ?int $size;
    private int $error;
    private ?string $clientFilename;
    private ?string $clientMediaType;
    private bool $moved = false;

    public function __construct(
        $streamOrFile,
        ?int $size = null,
        int $errorStatus = UPLOAD_ERR_OK,
        ?string $clientFilename = null,
        ?string $clientMediaType = null
    ) {
        if (!in_array($errorStatus, self::ERRORS, true)) {
            throw new InvalidArgumentException('Invalid error status for UploadedFile');
        }

        if ($errorStatus === UPLOAD_ERR_OK) {
            if (is_string($streamOrFile)) {
                $this->streamOrFile = $streamOrFile;
            } elseif ($streamOrFile instanceof StreamInterface) {
                $this->streamOrFile = $streamOrFile;
            } else {
                throw new InvalidArgumentException(
                    'Invalid stream or file provided for UploadedFile'
                );
            }
        } else {
            $this->streamOrFile = '';
        }

        $this->size = $size;
        $this->error = $errorStatus;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;
    }

    public function getStream(): StreamInterface
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }

        if ($this->streamOrFile instanceof StreamInterface) {
            return $this->streamOrFile;
        }

        $this->streamOrFile = new Stream($this->streamOrFile, 'r');

        return $this->streamOrFile;
    }

    public function moveTo(string $targetPath): void
    {
        if ($this->error !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Cannot move file due to upload error');
        }

        if ($this->moved) {
            throw new RuntimeException('File has already been moved');
        }

        if ($targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation');
        }

        $targetDirectory = dirname($targetPath);
        if (!is_dir($targetDirectory) || !is_writable($targetDirectory)) {
            throw new RuntimeException(
                sprintf('The target directory `%s` does not exist or is not writable', $targetDirectory)
            );
        }

        $sapi = PHP_SAPI;
        if (str_starts_with($sapi, 'cli') || empty($sapi)) {
            // CLI mode: move the file using stream operations
            if ($this->streamOrFile instanceof StreamInterface) {
                $dest = new Stream($targetPath, 'w');
                $source = $this->streamOrFile;
                $source->rewind();

                while (!$source->eof()) {
                    $dest->write($source->read(4096));
                }
            } else {
                if (!rename($this->streamOrFile, $targetPath)) {
                    throw new RuntimeException('Error moving uploaded file');
                }
            }
        } else {
            // SAPI mode: use move_uploaded_file
            if (!is_string($this->streamOrFile)) {
                throw new RuntimeException('Cannot move uploaded file; source is not a file path');
            }

            if (!move_uploaded_file($this->streamOrFile, $targetPath)) {
                throw new RuntimeException('Error moving uploaded file');
            }
        }

        $this->moved = true;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}

