<?php

return [
    /*
     * REVISION ALPHA holding team. Organización is hardcoded for this team only.
     */
    'revision_alpha_team_id' => (int) env('ORGANIZATION_REVISION_ALPHA_TEAM_ID', 2),

    /*
     * Suggestion only. It is not payroll and it is not added to the projection
     * until monthly_salary is set on the organization.
     */
    'subsistence' => [
        'minutes_per_call' => 10,
        'hourly_eur' => [
            'director' => 28,
            'assistant' => 12,
        ],
    ],
];
