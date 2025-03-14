<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Placeholder;

use Closure;
use Ultra\Data\Placeholder;
use Ultra\Data\Query;
use Ultra\Data\Query\Status;

enum Type: string {
	case Sequence               = ':';
	case Nullable               = '?';
	case Constant               = ':C';
	case ConstantNullable       = '?C';
	case String                 = ':s';
	case StringStrict           = ':S';
	case StringNullable         = '?s';
	case StringStrictNullable   = '?S';
	case Integer                = ':i';
	case IntegerStrict          = ':I';
	case IntegerNullable        = '?i';
	case IntegerStrictNullable  = '?I';
	case Unsigned               = ':u';
	case UnsignedStrict         = ':U';
	case UnsignedNullable       = '?u';
	case UnsignedStrictNullable = '?U';
	case Numeric                = ':n';
	case NumericStrict          = ':N';
	case NumericNullable        = '?n';
	case NumericStrictNullable  = '?N';
	case Double                 = ':d';
	case DoubleStrict           = ':D';
	case DoubleNullable         = '?d';
	case DoubleStrictNullable   = '?D';
	case Boolean                = ':b';
	case BooleanStrict          = ':B';
	case BooleanNullable        = '?b';
	case BooleanStrictNullable  = '?B';
	case Blob                   = ':z';
	case Quantifier             = ':q';
	case QuantifierUnquoted     = ':Q';
	case List                   = ':L';
	case ListNullable           = '?L';
	case Map                    = ':a';
	case MapNullable            = '?a';
	case MapUnquoted            = ':A';
	case MapUnquotedNullable    = '?A';
	case Keys                   = ':k';
	case KeysUnquoted           = ':K';
	case Values                 = ':v';
	case ValuesUnquoted         = ':V';
	case Fake                   = ':f';

	public function needList(): bool {
		return self::List == $this || self::ListNullable == $this;
	}

	public function needKeys(): bool {
		return self::Keys == $this || self::KeysUnquoted == $this;
	}

	public function needValues(): bool {
		return self::Values == $this || self::ValuesUnquoted == $this;
	}

	public function needMap(): bool {
		return match ($this) {
			self::Map,
			self::MapUnquoted,
			self::MapNullable,
			self::MapUnquotedNullable => true,
			default => false,
		};
	}

	public function fnQuote(Query $query): Closure {
		return match ($this) {
			self::Quantifier,
			self::Map,
			self::MapNullable,
			self::Keys,
			self::Values => fn($value) => $this->_quotedString($query, $value),
			self::QuantifierUnquoted,
			self::MapUnquoted,
			self::MapUnquotedNullable,
			self::KeysUnquoted,
			self::ValuesUnquoted => fn($value) => $this->_unquotedString($query, $value),
			default => Status::InvalidContext->error($this->value, __METHOD__),
		};
	}

	public function fromBoolean(Query $query, Placeholder $ph, bool $value): string|null {
		return match ($this) {
			self::Sequence, self::Nullable, self::List, self::ListNullable,
			self::Boolean, self::BooleanStrict, self::BooleanNullable, self::BooleanStrictNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable
				=> ($query->booleans)($value),
			self::String, self::StringNullable, self::StringStrictNullable
				=> '\''.(string) (int) $value.'\'',
			self::Integer, self::IntegerNullable, self::IntegerStrictNullable,
			self::Unsigned, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Double, self::DoubleNullable, self::DoubleStrictNullable
				=> (string) (int) $value,
			self::Fake
				=> $value ? '' : null,
			self::Blob
				=> (binary) $value,
			self::Numeric, self::NumericNullable, self::NumericStrictNullable
				=> $value ? '1' : Status::InvalidVariableValue->error('FALSE', $this->value, $ph->index),
			self::Constant, self::ConstantNullable, self::StringStrict, self::IntegerStrict,
			self::UnsignedStrict, self::NumericStrict, self::DoubleStrict
				=> Status::InvalidVariableType->error('boolean', $this->value, $ph->index),
			self::Quantifier, self::QuantifierUnquoted, self::Keys,
			self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> Status::InvalidContext->error($this->value, __METHOD__),
		};
	}

	public function fromInteger(Query $query, Placeholder $ph, int $value): string|null {
		return match ($this) {
			self::Sequence, self::Nullable, self::List, self::ListNullable,
			self::Integer, self::IntegerStrict, self::IntegerNullable, self::IntegerStrictNullable,
			self::Double, self::DoubleStrict, self::DoubleNullable, self::DoubleStrictNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable
				=> (string) $value,
			self::Unsigned, self::UnsignedStrict, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Numeric, self::NumericStrict, self::NumericNullable, self::NumericStrictNullable,
				=> $this->_setNaturalInteger($value),
			self::String, self::StringNullable, self::StringStrictNullable
				=> '\''.$value.'\'',
			self::Boolean, self::BooleanNullable, self::BooleanStrictNullable
				=> ($query->booleans)((bool) $value),
			self::Fake
				=> $value ? '' : null,
			self::Blob
				=> (binary) $value,
			self::Constant, self::ConstantNullable, self::StringStrict, self::BooleanStrict
				=> Status::InvalidVariableType->error(gettype($value), $this->value, $ph->index),
			self::Quantifier, self::QuantifierUnquoted, self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> Status::InvalidContext->error($this->value, __METHOD__),
		};
	}

	public function fromDouble(Query $query, Placeholder $ph, float $value): string|null {
		return match ($this) {
			self::Sequence, self::Nullable, self::List, self::ListNullable,
			self::Double, self::DoubleStrict, self::DoubleNullable, self::DoubleStrictNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable
				=> (string) $value,
			self::Integer, self::IntegerStrict, self::IntegerNullable, self::IntegerStrictNullable,
			self::Unsigned, self::UnsignedStrict, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Numeric, self::NumericStrict, self::NumericNullable, self::NumericStrictNullable,
				=> $this->_setFloatAsInt($value, $ph->index),
			self::String, self::StringNullable, self::StringStrictNullable
				=> '\''.$value.'\'',
			self::Boolean, self::BooleanNullable, self::BooleanStrictNullable
				=> ($query->booleans)((bool) $value),
			self::Fake
				=> $value ? '' : null,
			self::Blob
				=> (binary) $value,
			self::Constant, self::ConstantNullable, self::StringStrict, self::BooleanStrict
				=> Status::InvalidVariableType->error(gettype($value), $this->value, $ph->index),
			self::Quantifier, self::QuantifierUnquoted, self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> Status::InvalidContext->error($this->value, __METHOD__),
		};
	}

	public function fromString(Query $query, Placeholder $ph, string $value): string|null {
		return match ($this) {
			self::String, self::StringStrict, self::StringNullable, self::StringStrictNullable, self::List, self::ListNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable, self::Sequence, self::Nullable
				=> '\''.($query->escape)($value).'\'',
			self::Integer, self::IntegerNullable, self::IntegerStrictNullable,
			self::Unsigned, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Numeric, self::NumericNullable, self::NumericStrictNullable
				=> $this->_setIntFromString($value),
			self::Double, self::DoubleNullable, self::DoubleStrictNullable
				=> $this->_setFloatFromString($value),
			self::Boolean, self::BooleanNullable, self::BooleanStrictNullable
				=> ($query->booleans)((bool) $value),
			self::Fake
				=> $value ? '' : null,
			self::Quantifier, self::QuantifierUnquoted
				=> $this->fnQuote($query)($value),
			self::Constant, self::ConstantNullable
				=> $this->_setConstantString($query, $value),
			self::Blob
				=> (binary) $value,
			self::IntegerStrict, self::UnsignedStrict, self::NumericStrict, self::DoubleStrict, self::BooleanStrict
				=> Status::InvalidVariableType->error('string', $this->value, $ph->index),
			self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> Status::InvalidContext->error($this->value, __METHOD__),
		};
	}

	public function fromNull(Query $query, Placeholder $ph): string|null {
		return match ($this) {
			self::Sequence, self::String, self::List, self::Map, self::MapUnquoted
				=> '\'\'',
			self::Integer, self::Unsigned, self::Double
				=> '0',
			self::Boolean
				=> ($query->booleans)(false),
			self::Fake
				=> null,
			self::Constant, self::StringStrict, self::IntegerStrict, self::UnsignedStrict,
			self::Numeric, self::NumericStrict, self::DoubleStrict, self::BooleanStrict, self::Blob
				=> Status::InvalidVariableType->error('NULL', $this->value, $ph->index),
			self::Quantifier, self::QuantifierUnquoted,
			self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> Status::InvalidContext->error($this->value, __METHOD__),
			default
				=> 'NULL',
		};
	}

	private function _isQuantifierString(Query $query, mixed $value): bool {
		if (is_string($value) && preg_match($query->quantifiers, $value)) {
			return true;
		}

		return false;
	}

	private function _quotedString(Query $query, mixed $value): string {
		if (!$this->_isQuantifierString($query, $value)) {
			Status::InvalidArrayKeys->error();
		}

		return $query->start_quote.str_replace('.', $query->end_quote.'.'.$query->start_quote, $value).$query->end_quote;
	}

	private function _unquotedString(Query $query, mixed $value): string {
		if (!$this->_isQuantifierString($query, $value)) {
			Status::InvalidArrayKeys->error();
		}

		return $value;
	}

	private function _isNumeric(): bool {
		return match ($this) {
			self::Numeric,
			self::NumericNullable,
			self::NumericStrict,
			self::NumericStrictNullable => true,
			default => false,
		};
	}

	private function _isUnsigned(): bool {
		return match ($this) {
			self::Unsigned,
			self::UnsignedNullable,
			self::UnsignedStrict,
			self::UnsignedStrictNullable => true,
			default => false,
		};
	}

	private function _setNaturalInteger(int|string $value): string {
		if ($this->_isUnsigned()) {
			if ($value < 0) {
				Status::NotContainPositiveNumber->error($value);
			}
		}

		if ($this->_isNumeric()) {
			if ($value < 1) {
				Status::NotContainNaturalNumber->error($value);
			}
		}

		return (string) $value;
	}

	private function _setFloatFromString(string $value): string {
		$this->_checkNumeric($value);
		return $value;
	}

	private function _setIntFromString(string $value): string {
		$this->_checkNumeric($value);
		$this->_checkValidIntString($value);
		return $this->_setNaturalInteger($value);
	}

	private function _checkNumeric(string $value): void {
		if (!is_numeric($value)) {
			Status::StringNotNumeric->error($value);
		}
	}

	private function _checkValidIntString(string $value): void {
		$int = (int) $value;
		$float = (float) $value;

		if ($int != $float) {
			Status::FloatDataLoss->error($value);
		}
	}

	private function _setFloatAsInt(float $value, string $index): string {
		$int = (int) $value;

		if ($int != $value) {
			Status::FloatDataLoss->error($value, $index);
		}

		return (string) $int;
	}

	private function _isConstantString(Query $query, string $value): bool {
		if (preg_match($query->constants, $value)) {
			return true;
		}

		return false;
	}

	private function _setConstantString(Query $query, string $value): string {
		if ($this->_isConstantString($query, $value)) {
			return $value;
		}

		Status::InvalidCharacters->error($value);
	}
}
