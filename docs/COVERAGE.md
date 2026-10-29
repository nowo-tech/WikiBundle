# Coverage (REQ-TEST-003)

## Table of contents

- [Gates](#gates)
- [Justified exclusions](#justified-exclusions)

## Gates

| Language | Gate | Enforced by |
| -------- | ---- | ----------- |
| PHP | 100% lines of `src/` (Clover) | `make test-coverage-100` / `composer test-coverage-100` (`scripts/check-coverage.php --min-percent=100`), CI job **Code Coverage** |
| TypeScript | 100% lines, statements, functions and branches of `wiki.ts` | `coverage.thresholds` in `vitest.config.ts` (`pnpm run test:coverage` exits non-zero below it), `make test-ts`, CI job **Vitest (TypeScript)** |

`phpunit.xml.dist` measures all of `src/` (no excluded paths).

## Justified exclusions

`@codeCoverageIgnoreStart` / `@codeCoverageIgnoreEnd` is used only for defensive branches that cannot be triggered without breaking the OS or the dev dependencies. Each block is listed here; adding a new one requires a row in this table.

| Location | Branch | Why it cannot be covered |
| -------- | ------ | ------------------------ |
| `src/Controller/WikiManageController.php` `exportSpace()` | `mkdir()` of the export temp dir fails | Fresh random dir under `sys_get_temp_dir()`; only fails when the temp dir is unwritable |
| `src/Controller/WikiManageController.php` `exportSpace()` | delete a partial ZIP after a failed export | The ZIP only exists if `ZipArchive::close()` fails after writing |
| `src/Command/WikiExportCommand.php` `execute()` | `mkdir()` of the export temp dir fails | Same as above |
| `src/DependencyInjection/WikiExtension.php` `registerAiServices()` | `ai.enabled` without `symfony/ai-bundle` | `symfony/ai-agent` is a dev dependency, so `AgentInterface` always exists in the test run |
| `src/Interchange/WikiDocumentTreeReader.php` | `scandir()` returns `false` | Called only on directories just listed by the reader |
| `src/Interchange/WikiArchiveHelper.php` `extractZip()` | `mkdir()` of the extraction dir fails | Fresh random dir under `sys_get_temp_dir()` |
| `src/Interchange/WikiArchiveHelper.php` `extractZip()` | `ZipArchive::extractTo()` fails | Entries are validated first; only fails on a full or read-only disk |
| `src/Interchange/WikiArchiveHelper.php` | `ZipArchive::statIndex()` returns `false` | Index comes from `0..numFiles-1` of an opened archive |
