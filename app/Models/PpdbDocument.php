<?php

namespace App\Models;

use App\Enums\PpdbDocumentType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PpdbDocument extends Model
{
    protected $table = 'ppdb_documents';

    protected $fillable = [
        'prospective_student_id',
        'document_type',
        'disk',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'document_type' => PpdbDocumentType::class,
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<ProspectiveStudent, $this>
     */
    public function prospectiveStudent(): BelongsTo
    {
        return $this->belongsTo(ProspectiveStudent::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
