<?php

declare(strict_types=1);

namespace PlinCode\JobBoards\Contracts;

use PlinCode\JobBoards\Data\JobPostingDTO;

interface JobBoardClient
{
    /**
     * Fetch all job postings for a given company slug.
     *
     * @return list<JobPostingDTO>
     */
    public function fetchJobsForCompany(string $slug): array;

    /**
     * Validate that a slug exists on the provider.
     * Returns the company name if valid, null otherwise.
     */
    public function validateSlug(string $slug): ?string;

    /**
     * Fetch a description for the company.
     * Returns null if not available.
     */
    public function fetchCompanyDescription(string $slug): ?string;
}
