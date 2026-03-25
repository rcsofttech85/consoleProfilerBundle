# Security Policy

## Supported Versions

Security updates are provided for the following versions of the Console Profiler
Bundle.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |

## Reporting a Vulnerability

If you discover any security related issues, please email
<rcsofttech85@gmail.com> (or the corresponding maintainer email) instead of
using the issue tracker.

All security vulnerabilities will be promptly addressed.

Please report the following information:

* The type of issue (buffer overflow, SQL injection, cross-site scripting, etc.)
* The location of the issue (i.e. what file and on what line)
* The impact of the issue
* A proof of concept (PoC) or instructions on how to reproduce the issue

We will try our best to respond to your report within 48 hours.

## Best Practices

As a reminder, this bundle is a development/debugging tool. It is strongly
recommended to **never** enable this bundle in a production environment (where
`kernel.debug` is false) to prevent the unintentional exposure of server
environment variables, memory limits, and SQL statistics.

By default, the `exclude_in_prod` configuration mitigates this risk.
