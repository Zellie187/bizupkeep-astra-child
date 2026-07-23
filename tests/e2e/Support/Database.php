<?php

declare(strict_types=1);

namespace BizUpKeep\Tests\E2E\Support;

use PDO;

/**
 * Direct, read-only database access for assertions HTTP responses
 * alone can't make (e.g. "did a bizhub_workflow_instances row with
 * this status actually get created" - the HTTP layer only proves a
 * redirect happened, not that the right thing landed in the
 * database). Mirrors exactly what manual testing did throughout this
 * project via the portable mariadb client - just as PDO instead of
 * shelling out.
 */
final class Database
{
    private readonly PDO $pdo;

    public function __construct(
        private readonly string $prefix
    ) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Config::dbHost(),
            Config::dbPort(),
            Config::dbName()
        );

        $this->pdo = new PDO($dsn, Config::dbUser(), Config::dbPassword(), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    /**
     * @param array<int|string,mixed> $params
     *
     * @return array<string,mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        $row = $statement->fetch();

        return $row === false ? null : $row;
    }

    /**
     * @param array<int|string,mixed> $params
     *
     * @return array<int,array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * The most recently created workflow instance of a given type for
     * a specific subject (company) UUID - the pattern every test in
     * this suite uses to find "the workflow my form submission should
     * have just created", since the submission handlers never return
     * the new workflow's UUID to the client.
     *
     * @return array<string,mixed>|null
     */
    public function latestWorkflowForCompany(string $workflowType, string $companyUuid): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM ' . $this->table('bizhub_workflow_instances') . '
             WHERE workflow_type = ? AND subject_uuid = ?
             ORDER BY created_at DESC LIMIT 1',
            [$workflowType, $companyUuid]
        );
    }

    /**
     * The most recently created company for a given client's numeric
     * bizhub_clients.id.
     *
     * @return array<string,mixed>|null
     */
    public function latestCompanyForClient(int $clientId): ?array
    {
        return $this->fetchOne(
            'SELECT * FROM ' . $this->table('bizhub_companies') . '
             WHERE client_id = ? ORDER BY created_at DESC LIMIT 1',
            [$clientId]
        );
    }

    /**
     * Resolve a WordPress username to their numeric bizhub_clients.id -
     * every workflow/company lookup in this suite keys off that ID,
     * not the WordPress user ID (a different ID space entirely - see
     * the bug fixed twice in this codebase for exactly this
     * confusion).
     */
    public function clientIdForWpUsername(string $username): ?int
    {
        $user = $this->fetchOne(
            'SELECT ID FROM ' . $this->table('users') . ' WHERE user_login = ?',
            [$username]
        );

        if ($user === null) {
            return null;
        }

        $client = $this->fetchOne(
            'SELECT id FROM ' . $this->table('bizhub_clients') . ' WHERE wp_user_id = ?',
            [(int) $user['ID']]
        );

        return $client === null ? null : (int) $client['id'];
    }
}
