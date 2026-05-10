<?php

declare(strict_types=1);

namespace Empire2\GazeGhostwriter\Support;

use Throwable;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\Folder;
use Webklex\PHPIMAP\Support\FolderCollection;

/**
 * Webklex picks "path or short name" based on the configured delimiter
 * (often "/"). Many servers use "." (e.g. `INBOX.Sent`) — then
 * `getFolder('INBOX.Sent')` fails because the lookup falls back to name.
 */
final class ImapFolderResolver
{
    public static function resolve(Client $client, string $folderPathOrName): ?Folder
    {
        if ($folderPathOrName === '') {
            return null;
        }

        try {
            $folder = $client->getFolder($folderPathOrName);
            if ($folder !== null) {
                return $folder;
            }

            foreach (['.', '/'] as $delimiter) {
                if (str_contains($folderPathOrName, $delimiter)) {
                    $folder = $client->getFolder($folderPathOrName, $delimiter);
                    if ($folder !== null) {
                        return $folder;
                    }
                }
            }

            foreach (self::flattenAllFolders($client) as $candidate) {
                if ($candidate->path === $folderPathOrName || $candidate->name === $folderPathOrName) {
                    return $candidate;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return list<Folder>
     */
    public static function flattenAllFolders(Client $client): array
    {
        try {
            $tree = $client->getFolders(true, null, true);
            $flat = self::foldersFromCollection($tree);
            if ($flat !== []) {
                return $flat;
            }
        } catch (Throwable) {
            // Fall through to non-recursive list.
        }

        try {
            $fallback = $client->getFolders(false, null, true);
            $out = [];
            foreach ($fallback as $folder) {
                $out[] = $folder;
            }

            return $out;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return list<Folder>
     */
    private static function foldersFromCollection(FolderCollection $collection): array
    {
        $out = [];
        foreach ($collection as $folder) {
            $out[] = $folder;
            if ($folder->children->count() > 0) {
                $out = array_merge($out, self::foldersFromCollection($folder->children));
            }
        }

        return $out;
    }
}
