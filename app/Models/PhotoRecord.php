<?php

namespace App\Models;

use Database\Factories\PhotoRecordFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;

class PhotoRecord extends Model
{
    /** @use HasFactory<PhotoRecordFactory> */
    use HasFactory, HasUlids;

    // Fields that may be filled from user input; storage details, photo dates and submission time are set by the server
    protected $fillable = ['note'];

    // Where the files live never leaves the server
    protected $hidden = ['image_disk', 'image_path', 'display_path'];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',      // when RISE received the photo: the authoritative date
            'photo_taken_at' => 'datetime',    // camera clock time from EXIF, unverified; see photo_taken_offset
            'file_modified_at' => 'datetime',  // older uploads only: the file's last-modified time, unverified
            'image_size' => 'integer',
            'image_width' => 'integer',
            'image_height' => 'integer',
            'display_width' => 'integer',
            'display_height' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::deleted(function (PhotoRecord $record) {
            Storage::disk($record->image_disk)->delete(array_filter([$record->image_path, $record->display_path]));
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function monitoringPoint(): BelongsTo
    {
        return $this->belongsTo(MonitoringPoint::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(PhotoAnalysis::class);
    }

    public function latestAnalysis(): HasOne
    {
        return $this->hasOne(PhotoAnalysis::class)->latestOfMany('created_at');
    }

    // The file residents see: the private JPEG copy when there is one, otherwise the original
    public function viewPath(): string
    {
        return $this->display_path ?? $this->image_path;
    }

    public function viewMime(): string
    {
        return $this->display_path ? $this->display_mime : $this->image_mime;
    }

    public function viewWidth(): ?int
    {
        return $this->display_path ? $this->display_width : $this->image_width;
    }

    public function viewHeight(): ?int
    {
        return $this->display_path ? $this->display_height : $this->image_height;
    }
}
