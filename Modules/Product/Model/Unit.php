<?php
namespace Modules\Product\Model;

class Unit {
    public function __construct(
        public string $value,
        public string $label
    ) {}

    /** Returns the static list of allowed units matching the UI dropdown. */
    public static function all(): array {
        return [
            new self('mtr',    'Meter (mtr)'),
            new self('pcs',    'Pieces (pcs)'),
            new self('kg',     'Kilogram (kg)'),
            new self('bundle', 'Bundle'),
            new self('pkt',    'Packet (pkt)'),
            new self('roll',   'Roll'),
            new self('box',    'Box'),
            new self('set',    'Set'),
            new self('pair',   'Pair'),
            new self('spool',  'Spool'),
        ];
    }
}
