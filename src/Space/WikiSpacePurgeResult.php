<?php

declare(strict_types=1);

namespace Nowo\WikiBundle\Space;

/**
 * 1.50: the outcome of {@see WikiSpaceLifecycleInterface::purgeSpace()}.
 */
final readonly class WikiSpacePurgeResult
{
    /**
     * @param string $spaceId the space
     * @param bool $finished true: the space and everything in it are gone; false: the batch budget of this call ran out,
     *                       call again to go on (the space stays archived and marked as being purged)
     * @param int $pages pages deleted by this call
     * @param int $batches batches committed by this call
     * @param bool $found 1.55.1: whether the space existed when this call started (false: it was already gone and
     *                    nothing was done, see {@see self::alreadyGone()})
     * @param int $filesFailed 1.55.1: attachment files of this call whose delete failed (logged; they stay in the
     *                         attachment storage until `WikiAttachmentService::purgeStrayFiles()` runs)
     * @param int $filesTruncated 1.55.1: batches of this call whose `WikiPageFilesPurgedEvent` file list was cut by
     *                            `page_files.max_purge_files` (the host's own page-file sweep must find the rest)
     */
    public function __construct(
        public string $spaceId,
        public bool $finished,
        public int $pages,
        public int $batches,
        public bool $found = true,
        public int $filesFailed = 0,
        public int $filesTruncated = 0,
    ) {
    }

    /** 1.55.1: whether the space was already gone (an earlier call finished the erasure, or there never was one). */
    public function alreadyGone(): bool
    {
        return !$this->found;
    }

    /**
     * 1.55.1: whether files of the space may still be stored after this call (a failed delete or a truncated page-file
     * list): run the attachment and page-file sweeps before reporting the erasure complete.
     */
    public function needsFileSweep(): bool
    {
        return $this->filesFailed > 0 || $this->filesTruncated > 0;
    }
}
