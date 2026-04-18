# Quality Report

## Current Quality Baseline

This package currently targets:

- PHP 8.2+
- Laravel 10, 11, 12, and 13

## Verification

The repository includes:

- automated PHPUnit coverage for package integration and core auth flows
- Orchestra Testbench-based package tests
- Composer validation with `composer validate --strict`
- GitHub Actions CI on push and pull request

## Current CI Matrix

The test workflow runs against these combinations:

| PHP | Laravel | Testbench | PHPUnit |
| --- | ------- | --------- | ------- |
| 8.2 | 10.x | 8.x | 10.5 |
| 8.3 | 11.x | 9.x | 10.5 |
| 8.3 | 12.x | 10.x | 11.5 |
| 8.4 | 13.x | 11.x | 11.5 |

## Quality Gates

Changes should meet these checks before release:

- Composer metadata validates successfully
- the package test suite passes
- supported Laravel matrix jobs pass in GitHub Actions
- docs stay aligned with supported framework versions

## Notes

Laravel 9 support has been intentionally removed because current dependency resolution for that line is blocked by security advisories in the Laravel 9 ecosystem. The package support policy now starts at Laravel 10.
