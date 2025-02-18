<?php declare(strict_types=1);
/**
 * (c) 2005-2025 Dmitry Lebedev <dl@adios.ru>
 * This source code is part of the Ultra data package.
 * Please see the LICENSE file for copyright and licensing information.
 */
namespace Ultra\Data;

enum Insertion {
	case String;
	case StringStrict;
	case StringNull;
	case StringNullStrict;
	case Integer;
	case IntegerStrict;
	case IntegerNull;
	case IntegerNullStrict;
	case Double;
	case DoubleStrict;
	case DoubleNull;
	case DoubleNullStrict;
	case Boolean;
	case BooleanStrict;
	case BooleanNull;
	case BooleanNullStrict;
	case Auto;

	public static function get(string $marker): self {
		return match ($marker) {
			's'   => self::String,
			's.'  => self::StringStrict,
			'?s'  => self::StringNull,
			'?s.' => self::StringNullStrict,
			'i'   => self::Integer,
			'i.'  => self::IntegerStrict,
			'?i'  => self::IntegerNull,
			'?i.' => self::IntegerNullStrict,
			'd'   => self::Double,
			'd.'  => self::DoubleStrict,
			'?d'  => self::DoubleNull,
			'?d.' => self::DoubleNullStrict,
			'b'   => self::Boolean,
			'b.'  => self::BooleanStrict,
			'?b'  => self::BooleanNull,
			'?b.' => self::BooleanNullStrict,
			default => self::Auto,
		};
	}

	public function baseType(): self {
		return match ($this) {
			self::String,
			self::StringStrict,
			self::StringNull,
			self::StringNullStrict => self::String,
			self::Integer,
			self::IntegerStrict,
			self::IntegerNull,
			self::IntegerNullStrict => self::Integer,
			self::Double,
			self::DoubleStrict,
			self::DoubleNull,
			self::DoubleNullStrict => self::Double,
			self::Boolean,
			self::BooleanStrict,
			self::BooleanNull,
			self::BooleanNullStrict => self::Boolean,
			default => self::Auto,
		};
	}

	public function baseName(): string {
		return match ($this) {
			self::String,
			self::StringStrict,
			self::StringNull,
			self::StringNullStrict => self::String->name,
			self::Integer,
			self::IntegerStrict,
			self::IntegerNull,
			self::IntegerNullStrict => self::Integer->name,
			self::Double,
			self::DoubleStrict,
			self::DoubleNull,
			self::DoubleNullStrict => self::Double->name,
			self::Boolean,
			self::BooleanStrict,
			self::BooleanNull,
			self::BooleanNullStrict => self::Boolean->name,
			default => self::Auto->name,
		};
	}
	
	public function isAuto(): bool {
		return self::Auto == $this;
	}

	public function isString(): bool {
		return match ($this) {
			self::String,
			self::StringStrict,
			self::StringNull,
			self::StringNullStrict => true,
			default => false,
		};
	}

	public function isInteger(): bool {
		return match ($this) {
			self::Integer,
			self::IntegerStrict,
			self::IntegerNull,
			self::IntegerNullStrict => true,
			default => false,
		};
	}

	public function isDouble(): bool {
		return match ($this) {
			self::Double,
			self::DoubleStrict,
			self::DoubleNull,
			self::DoubleNullStrict => true,
			default => false,
		};
	}

	public function isBoolean(): bool {
		return match ($this) {
			self::Boolean,
			self::BooleanStrict,
			self::BooleanNull,
			self::BooleanNullStrict => true,
			default => false,
		};
	}

	public function isNullable(): bool {
		return match ($this) {
			self::StringNull,
			self::StringNullStrict,
			self::IntegerNull,
			self::IntegerNullStrict,
			self::DoubleNull,
			self::DoubleNullStrict,
			self::BooleanNull,
			self::BooleanNullStrict,
			self::Auto => true,
			default => false,
		};
	}

	public function isStrict(): bool {
		return match ($this) {
			self::StringStrict,
			self::StringNullStrict,
			self::IntegerStrict,
			self::IntegerNullStrict,
			self::DoubleStrict,
			self::DoubleNullStrict,
			self::BooleanStrict,
			self::BooleanNullStrict => true,
			default => false,
		};
	}
}
