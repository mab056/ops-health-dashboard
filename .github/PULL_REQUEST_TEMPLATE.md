## Summary

Describe clearly what this PR changes and why.

## Type of change

- [ ] `feat` - New feature
- [ ] `fix` - Bug fix
- [ ] `refactor` - Refactor without behavior changes
- [ ] `test` - Added/updated tests
- [ ] `docs` - Documentation only
- [ ] `chore` - Tooling/CI/build

## Related issues

- Closes #
- Related #

## Changes made

- 
- 
- 

## Testing

### Test strategy (TDD)

- [ ] RED: test added/updated and initially failing
- [ ] GREEN: minimal implementation to make tests pass
- [ ] REFACTOR: cleanup/refactor completed with tests still green

### Commands executed

- [ ] `composer test:unit`
- [ ] `composer test:integration`
- [ ] `composer phpcs`
- [ ] `composer analyse`

Relevant output/errors (required if something was not executed):

```text
Paste a concise output summary or concrete error (e.g. DB unreachable)
```

## Manual test steps

1. 
2. 
3. 

## Architecture checklist (required)

- [ ] No singleton introduced
- [ ] No static methods/properties introduced
- [ ] No `final` classes/methods introduced
- [ ] Constructor dependency injection respected
- [ ] Interface-first design respected where applicable
- [ ] No direct WordPress global access in business logic

## Security checklist (required)

- [ ] Inputs sanitized (`sanitize_text_field`, `sanitize_email`, `esc_url_raw`, `absint`, ...)
- [ ] Outputs escaped (`esc_html`, `esc_attr`, `esc_url`, `esc_js`)
- [ ] Capability checks present (`current_user_can('manage_options')`) for admin areas
- [ ] Nonce verified for forms/AJAX
- [ ] Anti-SSRF applied to external webhook/HTTP requests

## Regression checklist

- [ ] Checked `git diff --name-only` and `git diff`
- [ ] Test coverage updated for changed behavior
- [ ] No cron/scheduler regressions (no duplicates, self-healing still active)
- [ ] Sequential/parallel tooling compatibility verified

## Screenshots / recordings (UI changes)

<!-- Add screenshots or GIFs for HealthScreen, Alert Settings, Dashboard Widget, etc. -->

## Additional notes

<!-- Residual risks, technical trade-offs, follow-up work -->
