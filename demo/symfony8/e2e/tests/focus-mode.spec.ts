import { test, expect } from '@playwright/test';

/**
 * 1.41 — focus mode of the UiKit shell (UiKit >= 2.55, theme.focus_mode). The demo's UiKit has no focus mode yet: the
 * wiki then prints neither the toggle nor the "Pages" drawer button, and the tree keeps its place in the layout.
 * When UiKit is tagged, this file also covers the toggle (area "wiki") and the tree drawer.
 */
const deploy = '/tools/wiki/spaces/engineering/pages/deploy';

test('without focus mode in UiKit the wiki prints no toggle and the tree stays in the layout', async ({ page }) => {
  for (const path of [deploy, `${deploy}/edit`]) {
    await page.goto(path);
    await expect(page.locator('[data-nowo-ui-focus-mode]')).toHaveCount(0);
    await expect(page.getByTestId('wiki-focus-tree-toggle')).toHaveCount(0);
  }
  await page.goto(deploy);
  await expect(page.locator('[data-wiki-sidebar]')).toBeVisible();
});
