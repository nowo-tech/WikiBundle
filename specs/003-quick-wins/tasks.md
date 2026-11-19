# Tasks: 003-quick-wins (1.45)

- [x] T001 Configuration: `favourites`, `recents`, `backlinks`, `history.rendered_diff`, route `page_favourite`
- [x] T002 Entities and XML mapping: `WikiPageFavourite`, `WikiPageVisit`, `WikiPageReference`; schema left out when off; binary collation
- [x] T003 `QuickAccess\WikiQuickAccessStore` (DBAL writes in the runner, unique race as domain result, bounded prune)
- [x] T004 `QuickAccess\WikiQuickAccess` + `WikiReadableSpaces` (per-viewer filtering before the cut)
- [x] T005 `Controller\WikiFavouriteController` + `Transaction\WikiConflictAnswer` (rule 6)
- [x] T006 `Backlink\WikiPageReferenceIndex`, listener on `PAGE_SAVED`, `WikiBacklinks`, `wiki:backlinks:rebuild`
- [x] T007 `History\WikiRenderedRevisionDiff` (editor diff, per-viewer blocks, double sanitizing)
- [x] T008 Templates, CSS, translations (7 locales)
- [x] T009 Tests: unit, integration, database (SQLite, MySQL 8.4, MariaDB 11.4, PostgreSQL 16, two-connection races)
- [x] T010 Docs: USAGE, CONFIGURATION, UPGRADING, CHANGELOG [Unreleased], ROADMAP status, HIGH-LOAD
