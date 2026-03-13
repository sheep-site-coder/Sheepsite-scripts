# Done Sheeping

Wrap up the work session and save everything.

## Instructions

1. **Update the conversation snapshot** (`docs/conversation-snapshot.md`):
   - Update the "Current State" section with any new completed items
   - Update the "Buildings Configured" section if any new buildings were added
   - Update the "Next Steps" section
   - Update the date at the top

2. **Check if any other documentation needs updates**:
   - If a new building was added, confirm it's in `buildings.php`
   - If the Apps Script (dir-display-bridge.gs) changed, note it in the snapshot
   - If credential files changed, note it in the snapshot (but never commit actual credentials)

3. **Review all changes**:
   ```bash
   git status
   git diff --stat
   ```

4. **Commit everything** (skip credential files — they should be in .gitignore):
   - Stage all changes
   - Create a descriptive commit message summarizing what was done this session
   - Format: "Session: [brief description of main accomplishments]"

5. **Push to git**:
   ```bash
   git push
   ```

6. **Confirm completion**:
   - Show me what was committed
   - Confirm the push was successful
   - Say "Session complete! The flock is safe."
