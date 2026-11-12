# Agent Protocol

## Required startup steps

Read:

- `.ai/PROJECT_STATE.md`
- `.ai/GUARDRAILS.md`
- `.ai/CONTEXT.md` (if present)
- Open issues: `gh issue list --state open --json number,title,body,labels,assignees`

`PROJECT_STATE.md` holds only the current snapshot (what's working, what's broken, current decisions, next
milestone). Task history lives in closed issues.

## Issue workflow

Claiming an issue, collision checks, branching, moving a task to PR, completion requirements, stalled or
abandoned tasks, and post-merge cleanup all live in the user-level `implement-issue` skill
(`~/.claude/skills/implement-issue/`, linked into `~/.agents/skills/` for Codex). It reads this repo's specifics
from `.ai/*` and `docs/agents/`.

Without that skill (another machine, a cloud session): claim with `gh issue edit <n> --add-assignee @me`, work
on a branch named `ai/<agent>/<issue-number>-<slug>`, never directly on `main`, meet the test bar in
`.ai/GUARDRAILS.md`, update `PROJECT_STATE.md`, end the last commit with `Closes #<n>`, post a completion comment
(summary, files changed, how to test, risks or follow-ups), open the PR, then check out `main`. Merging is the
user's call, not the agent's.

## Releases

If the change should reach consuming apps, say in the PR whether it needs a new release tag (latest:
`git tag --sort=-v:refname | head -1`); tag only when the user asks.

## Unattended runs

Code changes never go directly on `main` in an unattended run. Docs/config changes may (see
`GUARDRAILS.md`).

In an unattended run, a message with no tool call ends the work until someone comes back. Don't end a turn
while work you were asked to do is still owed, in any of these ways:

- A summary of what was done that closes by announcing the next step, with no tool call.
- An offer to carry on "unless you'd prefer otherwise".
- A list of decisions for the user when, by your own account, none of them blocks the rest of the work.
- Deciding this is a good place to report because the turn has been long or a milestone is done.

Put status notes and recommendations on open decisions in the same message as your next tool call, and keep
going with whatever doesn't depend on the answer. The stops that are wanted: nothing can move without the
user, the blocker is deliberately protected from you, or the task is finished and its completion
requirements (`implement-issue` step 7) are posted. This never overrides the need to confirm risky or
destructive actions — in an unattended run, stop and leave an issue comment instead of taking them.
