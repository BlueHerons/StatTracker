Patches are welcome! Please read this guide for some tips on submitting a patch / pull request.

Code Style
----------
In general, Stat Tracker follows the [PSR-1](http://www.php-fig.org/psr/psr-1/) and [PSR-2](http://www.php-fig.org/psr/psr-2/) coding styles. Pull Requests MUST adhere to these guidelines

General points are:
- Indent using a 4 spaces! (set the TAB key to be 4 spaces in your IDE for a good result)
- Put a single space after `if`, `for`, etc. E.g. `if (true)`
- `else` and `else if` clauses should on a new line after the closing `}`
- No hard limit on line length, but 120 characters is a good target. Aim for readability.
- Use two slashes for comments : `// this is a comment`. These can be multiple lines for long comments.
- Please remove all non-necessary whitespace
  - `grep -nE "[[:space:]]+$" <filename>

Exceptions to the PSR Standards:
- (PHP) Put opening brace for classes and functions on the same line
  - `class FooBar {`
  - `public function functionName() {`
- (PHP) Include the closing tag `?>`

This style guide may be revised in the future
