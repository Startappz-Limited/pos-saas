<?php

/**
 * The rows of the isolation suite whose endpoints arrive in Phase 6 (`/api/v1`,
 * sync and M-Pesa). Written now so they're filled in as those endpoints are
 * built (Phase 2); until then they show as pending in every run.
 */
it('refuses an /api/v1 token for business A when it acts on business B')->todo();

it('refuses business B\'s records on every /api/v1 resource: index, show, update and delete')->todo();

it('never returns business B\'s rows to a sync pull for business A')->todo();

it('rejects a sync push for business A that carries business B\'s ids')->todo();

it('keeps an M-Pesa payment webhook within the business whose shortcode it names')->todo();
