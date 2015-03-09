Patches are welcome! Please read this guide for some tips on submitting a patch / pull request.

Code Style
----------

Please follow the these guidelines. Some are just preference, others are good practice.
- Indent using a tab (set tab = 4 spaces in your IDE for a good result)
- Put opening brace on the same line
  - `if ($condition) {` 
  - `.style-class { `
- `else` clauses on a new line after the closing `}`
- Put a single space after `if`, `for`, etc. E.g. `if (true)`
- Use two slashes for comments : `// this is a comment`
- No hard limit on line length, but 120 characters is a good target. Aim for readability.
- Please remove all non-necessary whitespace
  - `grep -nE "[[:space:]]+$" «filename»`
