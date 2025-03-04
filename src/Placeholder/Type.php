<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data\Placeholder;

use Closure;
use Exception;
use Ultra\Data\Placeholder;
use Ultra\Data\Query;

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

	public function isQuantifierString(Query $query, mixed $value): bool {
		if (is_string($value) && preg_match($query->quantifier, $value)) {
			return true;
		}

		return false;
	}

	public function quotedString(Query $query, mixed $value): string {
		if (!$this->isQuantifierString($query, $value)) {
			throw new Exception(message: 'Array keys are not suitable for use as quantifiers.', code: 390);
		}

		return $query->start_quote.str_replace('.', $query->end_quote.'.'.$query->start_quote, $value).$query->end_quote;
	}

	public function unquotedString(Query $query, mixed $value): string {
		if (!$this->isQuantifierString($query, $value)) {
			throw new Exception(message: 'Array keys are not suitable for use as quantifiers.', code: 390);
		}

		return $value;
	}

	public function fnQuote(Query $query): Closure {
		return match ($this) {
			self::Quantifier,
			self::Map,
			self::MapNullable,
			self::Keys,
			self::Values => fn($value) => $this->quotedString($query, $value),
			self::QuantifierUnquoted,
			self::MapUnquoted,
			self::MapUnquotedNullable,
			self::KeysUnquoted,
			self::ValuesUnquoted => fn($value) => $this->unquotedString($query, $value),
			default => throw new Exception('Недопустимый контекст \''.$this->value.'\' вызова метода '.__METHOD__.'.'),
		};
	}

	public function isNumeric(): bool {
		return match ($this) {
			self::Numeric,
			self::NumericNullable,
			self::NumericStrict,
			self::NumericStrictNullable => true,
			default => false,
		};
	}

	public function isUnsigned(): bool {
		return match ($this) {
			self::Unsigned,
			self::UnsignedNullable,
			self::UnsignedStrict,
			self::UnsignedStrictNullable => true,
			default => false,
		};
	}

	public function setNumericString(string $value): string {
		if (!is_numeric($value)) {
			throw new Exception('Строка \''.$value.'\' не является числовой');
		}

		if ($this->isUnsigned()) {
			if ($value < 0) {
				throw new Exception('Ожидалось, что числовая строка \''.$value.'\' будет содержать положительное число.');
			}
		}

		if ($this->isNumeric()) {
			if ($value <= 0) {
				throw new Exception('Числовая строка \''.$value.'\' не содержит число, которое относится к положительному натуральному численному ряду');
			}
		}

		return $value;
	}

	public function setConstantString(Query $query, string $value): string {
		if (($query->escape)($value) == $value) {
			return $value;
		}

		throw new Exception('Константная строка \''.$value.'\' содержит недопустимые символы.');
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
			self::Numeric, self::NumericNullable, self::NumericStrictNullable,
			self::Double, self::DoubleNullable, self::DoubleStrictNullable
				=> (string) (int) $value,
			self::Fake
				=> $value ? '' : null,
			self::Blob
				=> (binary) $value,
			self::Constant, self::ConstantNullable, self::StringStrict, self::IntegerStrict,
			self::UnsignedStrict, self::NumericStrict, self::DoubleStrict
				=> throw new Exception('Недопустисый тип \''.gettype($value).'\' для переменной заполнителя \''.$this->value.'\' c индексом '.$ph->index.':.'),
			self::Quantifier, self::QuantifierUnquoted, self::Keys,
			self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> throw new Exception('Недопустимый контекст \''.$this->value.'\' вызова метода '.__METHOD__.'.'),
		};
	}

	public function fromNumeric(Query $query, Placeholder $ph, int|float $value): string|null {
		return match ($this) {
			self::Sequence, self::Nullable, self::List, self::ListNullable,
			self::Integer, self::IntegerStrict, self::IntegerNullable, self::IntegerStrictNullable,
			self::Unsigned, self::UnsignedStrict, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Numeric, self::NumericStrict, self::NumericNullable, self::NumericStrictNullable,
			self::Double, self::DoubleStrict, self::DoubleNullable, self::DoubleStrictNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable
				=> (string) $value,
			self::String, self::StringNullable, self::StringStrictNullable
				=> '\''.$value.'\'',
			self::Boolean, self::BooleanNullable, self::BooleanStrictNullable
				=> ($query->booleans)((bool) $value),
			self::Fake
				=> $value ? '' : null,
			self::Blob
				=> (binary) $value,
			self::Constant, self::ConstantNullable, self::StringStrict, self::BooleanStrict
				=> throw new Exception('Недопустисый тип \''.gettype($value).'\' для переменной заполнителя \''.$this->value.'\' c индексом '.$ph->index.':.'),
			self::Quantifier, self::QuantifierUnquoted, self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> throw new Exception('Недопустимый контекст \''.$this->value.'\' вызова метода '.__METHOD__.'.'),
		};
	}

	public function fromString(Query $query, Placeholder $ph, string $value): string|null {
		return match ($this) {
			self::String, self::StringStrict, self::StringNullable, self::StringStrictNullable, self::List, self::ListNullable,
			self::Map, self::MapNullable, self::MapUnquoted, self::MapUnquotedNullable, self::Sequence, self::Nullable
				=> '\''.($query->escape)($value).'\'',
			self::Integer, self::IntegerNullable, self::IntegerStrictNullable, self::Unsigned, self::UnsignedNullable, self::UnsignedStrictNullable,
			self::Numeric, self::NumericNullable, self::NumericStrictNullable, self::Double, self::DoubleNullable, self::DoubleStrictNullable
				=> $this->setNumericString($value),
			self::Boolean, self::BooleanNullable, self::BooleanStrictNullable
				=> ($query->booleans)((bool) $value),
			self::Fake
				=> $value ? '' : null,
			self::Quantifier, self::QuantifierUnquoted
				=> $this->fnQuote($query)($value),
			self::Constant, self::ConstantNullable
				=> $this->setConstantString($query, $value),
			self::Blob
				=> (binary) $value,
			self::IntegerStrict, self::UnsignedStrict, self::NumericStrict, self::DoubleStrict, self::BooleanStrict
				=> throw new Exception('Недопустисый тип \'string\' для переменной заполнителя \''.$this->value.'\' c индексом '.$ph->index.':.'),
			self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> throw new Exception('Недопустимый контекст \''.$this->value.'\' вызова метода '.__METHOD__.'.'),
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
				=> throw new Exception('Недопустисый тип \'NULL\' для переменной заполнителя \''.$this->value.'\' c индексом '.$ph->index.':.'),
			self::Quantifier, self::QuantifierUnquoted,
			self::Keys, self::KeysUnquoted, self::Values, self::ValuesUnquoted
				=> throw new Exception('Недопустимый контекст \''.$this->value.'\' вызова метода '.__METHOD__.'.'),
			default
				=> 'NULL',
		};
	}
}
