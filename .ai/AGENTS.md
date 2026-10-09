# Agent Protocol

## Required startup steps

1. Read:
    - `.ai/PROJECT_STATE.md`
    - `.ai/GUARDRAILS.md`
    - `.ai/CONTEXT.md` (if present)
    - Open issues: `gh issue list --state open --json number,title,body,labels,assignees`

`PROJECT_STATE.md` holds only the current snapshot (what's working, what's broken, current decisions, next
milestone). Task history lives in closed issues — query the tracker instead of keeping a local archive file.

2. Identify project type and toolchain (confirm from `.ai/CONTEXT.md` — don't guess).
3. Select exactly one open, unassigned issue, or create a new one.
4. Claim the issue before coding: `gh issue edit <n> --add-assignee @me`.

## Branching rules

- One branch per task.
- Branch naming: `ai/claude/<issue-number>-<slug>`.
- Never work directly on main/master.

## Collision avoidance

- Do not modify files already touched by another assigned (claimed) issue.
- If unavoidable, stop and coordinate by commenting on both issues.
- Claiming is not atomic. Immediately before assigning yourself, re-check the issue. If a different agent
  claimed it or touched the same files since you last checked, do not assign yourself; pick a different
  issue or stop and surface the conflict.

## Allowed scope

- Work only within the claimed issue's scope.
- No opportunistic fixes or refactors.

## Moving a task to PR status

This is a solo-developer-plus-agents project; PRs here are not a review gate — they exist mainly so the
human maintainer has a diff to look at before merging. Keep this step lightweight.

A task only moves from claimed to PR once all of the following are true:

- All new and existing relevant tests pass locally.
- If the change is non-trivial (new feature, non-obvious logic, anything touching a security/data boundary,
  or a diff you'd want a second pair of eyes on — use judgment), run `/code-review` and then `/codex:review`,
  and address or consciously dismiss their findings before opening the PR.
- If the change should reach consuming apps, say in the PR whether it needs a new release tag (latest is
  `v1.0.3`); tag only when the user asks.
- The branch is pushed.
- Completion requirements below are posted as a comment on the issue.
- The PR is opened, references the issue (so merging auto-closes it), and its link is posted back to the
  issue.
- Return to the main branch so the working tree isn't left sitting on the task branch.

## After a PR merges

If the repo doesn't auto-delete branches on merge:

- Delete the remote branch.
- Delete the local branch if checked out elsewhere.
- Merged branches should not be left around — history is preserved on main regardless.

## Stalled or wrong-approach tasks

- If an issue cannot proceed due to an external dependency or missing information, comment with the reason.
  Leave yourself assigned if you expect to resume; if waiting on someone else, apply whatever this repo's
  "needs info" label is.
- If a task is abandoned because the approach was wrong or it's no longer needed, do not leave yourself
  assigned. Comment "abandoned: <why>", remove yourself as assignee, then stop and ask whether to close the
  issue or leave it open for someone else. Return to the main branch before stopping.

## Completion requirements

Each completed task must provide, as an issue comment:

- Summary: what changed and why
- Files changed: list
- How to test: exact commands
- Risks or follow-ups
- Confirmation that `PROJECT_STATE.md` was updated

Then close the issue (or let the merged PR's auto-close do it).
