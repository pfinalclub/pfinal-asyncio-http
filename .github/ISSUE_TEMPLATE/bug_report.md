---
name: Bug Report
about: Create a report to help us improve
title: '[BUG] '
labels: bug
assignees: ''
---

**Describe the bug**
A clear and concise description of what the bug is.

**To Reproduce**
Steps to reproduce the behavior:

```php
<?php
use PFinal\AsyncioHttp\Client;
use function PfinalClub\Asyncio\run;

run(function() {
    $client = new Client();
    // Your code here
});
```

**Expected behavior**
A clear and concise description of what you expected to happen.

**Actual behavior**
What actually happened.

**Error messages**
```
Paste any error messages here
```

**Environment:**
- OS: [e.g., Ubuntu 22.04, macOS 14.0]
- PHP Version: [e.g., 8.2.10]
- Package Version: [e.g., 3.0.0]
- pfinal-asyncio Version: [e.g., 3.0.0]
- Workerman Version: [e.g., 4.1.0]
- Event Loop: [e.g., Select, Event, Ev]

**Additional context**
Add any other context about the problem here.

**Possible Solution**
If you have ideas on how to fix the issue, please describe them here.

