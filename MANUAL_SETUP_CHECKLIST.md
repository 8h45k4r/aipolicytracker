# Setup checklist: independent reviewer role

Steps the lead maintainer must complete to make the independent data reviewer role effective.
This file is not linked from the public site. Check off each item in a commit when it is done.

## Joyce Loksee Ho — independent data reviewer

- [ ] **Declaration of interest received.** Joyce sends a written declaration of every interest
      a reader could consider relevant (employer, financial stakes, advisory roles, advocacy
      positions). Without this, her roster entry stays `published: false` and the data validator
      rejects verifications in her name.
- [ ] **Roster entry published.** Update `data/reviewers/joyce-loksee-ho.yaml`: replace the
      placeholder interest text with the actual declaration, set `published: true`, and merge via
      pull request.
- [ ] **GitHub handle confirmed.** Replace `<JOYCE_GITHUB_HANDLE>` in `.github/CODEOWNERS` with
      her actual GitHub username. Until this is done, CODEOWNERS review requests for `/data/` do
      not reach her.
- [ ] **GitHub collaborator added.** Invite Joyce's GitHub account to the repository with at least
      triage (or write) access so that she can submit reviews and approve pull requests.
- [ ] **Branch protection updated.** Configure the `main` branch protection rule to require an
      approving review from the CODEOWNERS entry for `/data/`. This enforces rule (b) in
      `CONFLICT_OF_INTEREST.md`: the maintainer cannot merge a data change alone.

## Bus factor provisions

- [ ] **Second admin.** A second person holds GitHub organisation owner or repository admin access.
- [ ] **Domain registrar.** At least two people can access the domain registrar.
- [ ] **Hosting credentials.** Deployment and hosting credentials are documented in a sealed
      envelope, a shared password manager vault, or equivalent, accessible to the independent
      reviewer or second admin.

## When this is done

Once all items under "Joyce Loksee Ho" are checked, the independent reviewer role is active. Update
the status notes in `GOVERNANCE.md` and `CONFLICT_OF_INTEREST.md` to remove the "not yet enforced"
caveats and update the independence page (`resources/views/site/pages/independence.blade.php`).
