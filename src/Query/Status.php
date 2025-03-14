<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Query;

use Exception;
use Ultra\Condition;

enum Status: int implements Condition {
	case OK                        = 350;
	case PlaceholdersWithoutValue  = 351;
	case MissingSharedValue        = 352;
	case UnexpectedSharedValueType = 353;
	case TypeChangeDetected        = 354;
	case UnexpectedPlaceholderType = 355;
	case NotMatchPlaceholderType   = 356;
	case UnexpectedValueType       = 357;
	case InvalidArrayKeys          = 358;
	case InvalidContext            = 359;
	case NotContainNaturalNumber   = 360;
	case NotContainPositiveNumber  = 361;
	case StringNotNumeric          = 362;
	case InvalidCharacters         = 363;
	case InvalidVariableType       = 364;
	case InvalidVariableValue      = 365;
	case FloatDataLoss             = 366;

	public function isFatal(): bool {
		return false;
	}

	public function error(int|float|string|bool|null ...$values): never {
		throw new Exception(message: $this->message($values), code: $this->value);
	}

	public function message(array $values = []): string {
		$message = $this->_message();

		if (count($values) > 0) {
			$search = [];
			$replace = [];

			foreach ($values as $key => $value) {
				$search[] = '{'.$key.'}';
				$replace[] = $value;
			}

			$message = str_replace($search, $replace, $message);
		}

		if (preg_match('/\{\d+\}/', $message) > 0) {
			$message = preg_replace('/\{\d+\}/', '...', $message);
		}

		return $message;
	}

	private function _message(): string {
		return match($this) {
			self::PlaceholdersWithoutValue  => 'When filling the "{0}" query, some placeholders were left without a value: {1}.',
			self::MissingSharedValue        => 'The query "{0}" is missing a shared value required for the placeholders with indices \'0\' and \'1\'.',
			self::UnexpectedSharedValueType => 'Unexpected shared value type in query "{0}". Expected \'array\', got \'{1}\'.',
			self::TypeChangeDetected        => 'While parsing SQL statement "{0}", an invalid change of placeholder type from \'{1}\' to \'{2}\' was detected.',
			self::UnexpectedPlaceholderType => 'Unexpected placeholder type \'{0}\' with index \'{1}\'.',
			self::NotMatchPlaceholderType   => 'The value type \'array\' does not match the placeholder type \'{0}\'.',
			self::UnexpectedValueType       => 'Unexpected data option value type \'{0}\'.',
			self::InvalidArrayKeys          => 'Array keys are not suitable for use as quantifiers.',
			self::InvalidContext            => 'Invalid context \'{0}\' for calling method {1}.',
			self::NotContainNaturalNumber   => 'The numeric string \'{0}\' does not contain a number that belongs to a positive natural number series.',
			self::NotContainPositiveNumber  => 'Variable was expected to contain a positive number. Contains \'{0}\'.',
			self::StringNotNumeric          => 'The string \'{0}\' is not numeric.',
			self::InvalidCharacters         => 'The constant string \'{0}\' contains invalid characters.',
			self::InvalidVariableType       => 'Invalid type \'{0}\' for placeholder variable \'{1}\' with index \'{2}:\'.',
			self::InvalidVariableValue      => 'Invalid value \'{0}\' for placeholder variable \'{1}\' with index \'{2}:\'.',
			self::FloatDataLoss             => 'Attempting to pass a floating point number \'{0}\' to an integer placeholder \'{1}:\' with data loss.',
			self::OK                        => 'OK',
		};
	}
}
