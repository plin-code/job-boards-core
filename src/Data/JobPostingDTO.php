<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Data;

readonly class JobPostingDTO
{
    /**
     * @param  array<string, mixed>  $rawPayload
     */
    public function __construct(
        public string $externalId,
        public string $title,
        public ?string $location,
        public string $url,
        public ?string $department,
        public array $rawPayload,
    ) {}

    /**
     * The keys returned here are public API: consumers persist them as-is.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'external_id' => $this->externalId,
            'title' => $this->title,
            'location' => $this->location,
            'url' => $this->url,
            'department' => $this->department,
            'raw_payload' => $this->rawPayload,
        ];
    }
}
