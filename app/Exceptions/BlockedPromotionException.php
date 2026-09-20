<?php

namespace App\Exceptions;

use App\Models\SchoolClass;
use DomainException;

/**
 * Satu atau lebih kelas sumber tidak memiliki aturan kenaikan kelas yang
 * valid (aturan belum dikonfigurasi, aturan tidak aktif, atau kelas tujuan
 * pada aturan tidak tersedia). Promosi dibatalkan secara menyeluruh sebelum
 * ada tulisan apapun; admin harus memperbaiki aturan kelas sumber terlebih
 * dahulu lalu mencoba promosi kembali.
 */
class BlockedPromotionException extends DomainException
{
    /**
     * @param  list<array{
     *     source_class: SchoolClass,
     *     reason_label: string,
     *     student_count: int,
     * }>  $blockedMappings
     */
    public function __construct(
        private readonly array $blockedMappings,
    ) {
        parent::__construct('Promosi tidak dapat diproses: terdapat kelas sumber tanpa aturan aktif yang valid.');
    }

    /**
     * @return list<array{
     *     source_class: SchoolClass,
     *     reason_label: string,
     *     student_count: int,
     * }>
     */
    public function blockedMappings(): array
    {
        return $this->blockedMappings;
    }
}
