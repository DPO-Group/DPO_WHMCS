# Changelog

## [[v1.2.0]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.2.0)

### Added

- Compatibility update for WHMCS 8.13.1 and PHP 8.3.
- Updated Common Library to version 1.0.3.
- Added `transactionSource` to the payment request.

### Fixed

- Removed the defaulted 'test' literal from the service type description in the payment request.
- Fixed an issue where invoices remained in Unpaid status after successful payment callback processing.
- Resolved callback parameter handling and URL routing failures during payment verification.

## [[v1.1.0]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.1.0)

### Added

- Compatibility update for WHMCS 8.11.2 and PHP 8.2.
- Integration with the DPO Pay common library.
- Code quality improvements.

## [[v1.0.4]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.0.4)

### Added

- Support for WHMCS 8.8.0 and PHP 8.1.

### Fixed

- Decline transactions if the reference and order ID don't match.

## [[v1.0.3]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.0.3)

### Fixed

- Hotfix whitespace issue.

## [[v1.0.2]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.0.2)

### Improved

- Error message handling.
- Token creation process.

### Fixed

- Invalid redirect issue.

### Added

- `<TransactionSource>` support.

## [[v1.0.1]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.0.1)

### Fixed

- Bug related to test mode and merchant tokens.

## [[v1.0.0]](https://github.com/DPO-Group/DPO_WHMCS/releases/tag/v1.0.0)

### Added

- Initial release.
