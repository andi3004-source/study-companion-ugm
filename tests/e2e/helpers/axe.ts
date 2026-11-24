import AxeBuilder from '@axe-core/playwright';
import { Page, expect } from '@playwright/test';

export interface CheckA11yOptions {
  disabledRules?: string[];
  includedImpacts?: ('minor' | 'moderate' | 'serious' | 'critical')[];
}

/**
 * Runs axe-core accessibility checks against the active page state.
 * Targets WCAG 2.0, 2.1, 2.2 Level A and AA standards.
 */
export async function checkA11y(page: Page, contextName: string, options: CheckA11yOptions = {}) {
  const builder = new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']);

  if (options.disabledRules && options.disabledRules.length > 0) {
    builder.disableRules(options.disabledRules);
  }

  const results = await builder.analyze();

  const violations = options.includedImpacts
    ? results.violations.filter((v) => v.impact && options.includedImpacts!.includes(v.impact))
    : results.violations;

  expect(
    violations,
    `Accessibility violations found on [${contextName}]:\n${violations
      .map(
        (v) =>
          `- [${v.impact?.toUpperCase()}] ${v.id}: ${v.description} (${v.nodes.length} occurrences)\n  Help: ${v.helpUrl}`
      )
      .join('\n')}`
  ).toEqual([]);
}
