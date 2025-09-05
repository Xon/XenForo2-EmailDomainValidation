# Email domain validation

Improves email validation in the following ways:
- More extensive typo detection
- Checks the domain-part of an email is "valid" and can receive email (ie has an MX/A record).

Provides a "Test email address validity"  (/admin.php?tools/test-email-address-validity) Page to give in-depth reporting of why an email is rejected
- Supports reporting issues if [Signup abuse detection and blocking](https://atelieraphelion.com/products/signup-abuse-detection-and-blocking.64/) add-on would block the email address during registration
- With XenForo 2.3.4+ this page respects the `Perform checks and tests` admin permission.

### Options
- `Admin user edits skip additional DNS-validation of email domains` (default: false)