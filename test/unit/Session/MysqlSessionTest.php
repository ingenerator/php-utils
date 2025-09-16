<?php
/**
 * @author    Craig Gosman <craig@ingenerator.com>
 * @licence   BSD-3-Clause
 */

namespace test\unit\Ingenerator\PHPUtils\Session;


use Closure;
use Ingenerator\PHPUtils\Session\MysqlSession;
use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use UnexpectedValueException;

class MysqlSessionTest extends TestCase
{
    private array $old_ini_vars = [];

    public function test_it_is_initialisable()
    {
        $this->assertInstanceOf(MysqlSession::class, $this->newSubject());
    }

    public static function provider_validate_invalid_sid(): array
    {
        return [
            'with path injection attempt' => [
                [],
                '../../../../../../../../../../../../../../windows/win.ini',
            ],
            'with url injection attempt' => [
                [],
                'http://some-inexistent-website.acu/some_inexistent_file_with_long_name?.jpg',
            ],
            'with SQL injection attempt' => [
                [],
                "'; TRUNCATE sessions;",
            ],
            'with invalid chars (in default bits per character)' => [
                [],
                str_pad('v', 32, 'a'),
            ],
            'with SID too short (with default length configuration)' => [
                [],
                str_repeat('a', 31),
            ],
            'with SID too long (with default length configuration)' => [
                [],
                str_repeat('a', 33),
            ],
            'with invalid characters (using custom bits)' => [
                ['session.sid_bits_per_character' => '5'],
                str_pad(',', 32, 'a'),
            ],
            'too long (with custom length)' => [
                ['session.sid_length' => '44'],
                str_repeat('a', 45),
            ],
            'too short (with custom length)' => [
                ['session.sid_length' => '44'],
                str_repeat('a', 43),
            ],
        ];
    }

    #[DataProvider('provider_validate_invalid_sid')]
    public function test_it_rejects_invalid_session_ids_without_checking_database(array $config, string $sid): void
    {
        $this->configurePhpIniVars($config);
        $subject = $this->newSubject(pdo: $this->mockPDOExpectingNoCalls());
        $this->assertFalse($subject->validateId($sid));
    }

    public static function provider_validate_own_sid(): array
    {
        return [
            'with default config' => [
                [],
                '/^[0-9a-f]{32}$/',
            ],
            'with 5 bits per char' => [
                ['session.sid_bits_per_character' => '5'],
                '/^[0-9a-v]{32}$/',
            ],
            'with 6 bits per char' => [
                ['session.sid_bits_per_character' => '6'],
                '/^[0-9a-zA-Z,-]{32}$/',
            ],
            'with custom length' => [
                ['session.sid_length' => '22'],
                '/^[0-9a-f]{22}$/',
            ],
            'with custom length and chars' => [
                ['session.sid_length' => '22', 'session.sid_bits_per_character' => '5'],
                '/^[0-9a-v,-]{22}$/',
            ],
        ];
    }

    #[DataProvider('provider_validate_own_sid')]
    public function test_sid_that_it_creates_is_valid(array $config, string $expected_pattern): void
    {
        $this->configurePhpIniVars($config);

        // This mocking isn't very nice - it is coupled to the implementation details of the SQL queries and the
        // results the class expects (and how it fetches them internally). I've tried to limit that as much as possible,
        // it would obviously be better if this ran as an integration test against an actual mysql instance - however
        // really here I only want to test that the validation is done against the database e.g. not short-circuited by
        // the pattern matching.
        $pdo = $this->mockPDOToPrepareQueries(function ($query) {
            $result = $this->createMock(PDOStatement::class);

            if (str_starts_with($query, 'INSERT INTO `sessions`')) {
                // Result of the query is never read
                return $result;
            }

            if (str_starts_with($query, 'SELECT GET_LOCK')) {
                // Just needs to return 1 to show that the lock is acquired. Note that validateSid does not release the
                // lock if it successfully loads a session (it'll be released on session_write_close or end of request)
                $result->expects($this->once())->method('fetchColumn')->willReturn('1');
                return $result;
            }

            if (str_starts_with($query, 'SELECT `session_data`')) {
                // Just needs to return some data, even empty
                $result->expects($this->once())->method('fetchAll')->willReturn([['session_data' => serialize([])]]);
                return $result;
            }

            throw new UnexpectedValueException('Un-mocked query '.$query);
        });

        $subject = $this->newSubject(pdo: $pdo);

        $sid = $subject->create_sid();
        // Sanity check that the settings applied as expected
        $this->assertMatchesRegularExpression($expected_pattern, $sid, 'Should generate SID in expected format');

        $this->assertTrue($subject->validateId($sid), 'Should validate SID "'.$sid.'"');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Force to the normal default configs
        $this->configurePhpIniVars([
            'session.sid_bits_per_character' => '4',
            'session.sid_length' => '32',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        foreach ($this->old_ini_vars as $key => $value) {
            @ini_set($key, $value);
        }
    }

    protected function newSubject(
        ?PDO $pdo = null
    )
    {
        return new MysqlSession(
            $pdo ?? $this->mockPDOExpectingNoCalls(),
            'insecure-salt',
        );
    }

    private function mockPDOExpectingNoCalls(): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->never())->method($this->anything());
        return $pdo;
    }

    private function configurePhpIniVars(array $config): void
    {
        foreach ($config as $setting => $value) {
            $this->old_ini_vars[$setting] = @ini_set($setting, $value);
        }
    }

    private function mockPDOToPrepareQueries(Closure $callback): PDO
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->expects($this->any())->method('prepare')->willReturnCallback($callback);
        return $pdo;
    }

}
