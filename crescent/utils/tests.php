<?php

namespace Crescent\Utils;

/**
 * Biblioteca de testes minimalista para o CrescentPHP.
 *
 * Não requer nenhuma dependência externa — funciona em hospedagem de R$10/mês.
 *
 * Uso:
 *
 *   use Crescent\Utils\Tests;
 *
 *   Tests::describe('UserModel', function () {
 *
 *       Tests::it('deve retornar todos os usuários', function () {
 *           $users = UserModel::all();
 *           Tests::expect(is_array($users))->toBe(true);
 *       });
 *
 *       Tests::it('deve criar um usuário', function () {
 *           $id = UserModel::insert(['name' => 'Ana', 'email' => 'ana@test.com']);
 *           Tests::expect($id)->toBeGreaterThan(0);
 *       });
 *   });
 *
 *   Tests::run();
 */
class Tests
{
    private static array  $suites  = [];
    private static string $current = '';
    private static int    $passed  = 0;
    private static int    $failed  = 0;
    private static array  $errors  = [];

    // ─── Definição ────────────────────────────────────────────────────────────

    /**
     * Agrupa testes relacionados.
     */
    public static function describe(string $name, callable $callback): void
    {
        static::$suites[$name] = $callback;
    }

    /**
     * Define um caso de teste individual.
     */
    public static function it(string $description, callable $test): void
    {
        $suite = static::$current;

        try {
            $test();
            static::$passed++;
            static::print("  ✓ {$description}", 'green');
        } catch (\Throwable $e) {
            static::$failed++;
            static::$errors[] = "[{$suite}] {$description}: " . $e->getMessage();
            static::print("  ✗ {$description}", 'red');
            static::print("    → " . $e->getMessage(), 'yellow');
        }
    }

    // ─── Expects ─────────────────────────────────────────────────────────────

    /**
     * Cria uma assertion fluente.
     */
    public static function expect(mixed $actual): Expectation
    {
        return new Expectation($actual);
    }

    // ─── Execução ─────────────────────────────────────────────────────────────

    /**
     * Executa todos os suites registrados e exibe o relatório.
     */
    public static function run(): void
    {
        static::$passed = 0;
        static::$failed = 0;
        static::$errors = [];

        static::print("\n🌙 CrescentPHP Tests\n", 'bold');

        foreach (static::$suites as $name => $callback) {
            static::$current = $name;
            static::print("\n{$name}", 'bold');
            $callback();
        }

        $total = static::$passed + static::$failed;
        static::print("\n─────────────────────────────────────────", '');
        static::print("  Total : {$total}", '');
        static::print("  Passed: " . static::$passed, 'green');
        static::print("  Failed: " . static::$failed, static::$failed > 0 ? 'red' : 'green');

        if (!empty(static::$errors)) {
            static::print("\nFalhas:", 'red');
            foreach (static::$errors as $err) {
                static::print("  • {$err}", 'red');
            }
        }

        static::print('');

        // Código de saída para integração com CI
        if (static::$failed > 0) {
            exit(1);
        }
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Falha o teste atual explicitamente.
     */
    public static function fail(string $message = 'Falha explícita'): void
    {
        throw new \RuntimeException($message);
    }

    /**
     * Ignora o teste atual.
     */
    public static function skip(string $reason = ''): void
    {
        throw new \RuntimeException('SKIP' . ($reason ? ": {$reason}" : ''));
    }

    private static function print(string $msg, string $color = ''): void
    {
        $colors = [
            'green'  => "\033[32m",
            'red'    => "\033[31m",
            'yellow' => "\033[33m",
            'bold'   => "\033[1m",
        ];
        $reset = "\033[0m";

        if (PHP_SAPI === 'cli') {
            $prefix = $colors[$color] ?? '';
            echo $prefix . $msg . ($prefix ? $reset : '') . PHP_EOL;
        } else {
            // Browser / hospedagem sem CLI
            echo htmlspecialchars($msg) . '<br>';
        }
    }
}

// ─── Expectation fluent API ───────────────────────────────────────────────────

class Expectation
{
    public function __construct(protected readonly mixed $actual) {}

    public function toBe(mixed $expected): static
    {
        if ($this->actual !== $expected) {
            throw new \RuntimeException(
                "Esperava: " . $this->stringify($expected) .
                " — obteve: " . $this->stringify($this->actual)
            );
        }
        return $this;
    }

    public function toEqual(mixed $expected): static
    {
        if ($this->actual != $expected) {
            throw new \RuntimeException(
                "Esperava (==): " . $this->stringify($expected) .
                " — obteve: " . $this->stringify($this->actual)
            );
        }
        return $this;
    }

    public function toBeTrue(): static
    {
        return $this->toBe(true);
    }

    public function toBeFalse(): static
    {
        return $this->toBe(false);
    }

    public function toBeNull(): static
    {
        return $this->toBe(null);
    }

    public function toBeGreaterThan(int|float $value): static
    {
        if (!($this->actual > $value)) {
            throw new \RuntimeException("{$this->actual} não é maior que {$value}");
        }
        return $this;
    }

    public function toBeLessThan(int|float $value): static
    {
        if (!($this->actual < $value)) {
            throw new \RuntimeException("{$this->actual} não é menor que {$value}");
        }
        return $this;
    }

    public function toContain(mixed $item): static
    {
        if (is_array($this->actual)) {
            if (!in_array($item, $this->actual, true)) {
                throw new \RuntimeException("Array não contém: " . $this->stringify($item));
            }
        } elseif (is_string($this->actual)) {
            if (!str_contains($this->actual, (string) $item)) {
                throw new \RuntimeException("String não contém: {$item}");
            }
        } else {
            throw new \RuntimeException('toContain requer array ou string');
        }
        return $this;
    }

    public function toHaveCount(int $count): static
    {
        $actual = is_array($this->actual) ? count($this->actual) : strlen((string) $this->actual);
        if ($actual !== $count) {
            throw new \RuntimeException("Esperava tamanho {$count}, obteve {$actual}");
        }
        return $this;
    }

    public function toBeInstanceOf(string $class): static
    {
        if (!($this->actual instanceof $class)) {
            $type = is_object($this->actual) ? get_class($this->actual) : gettype($this->actual);
            throw new \RuntimeException("Esperava instância de {$class}, obteve {$type}");
        }
        return $this;
    }

    public function toBeString(): static
    {
        if (!is_string($this->actual)) {
            throw new \RuntimeException("Esperava string, obteve " . gettype($this->actual));
        }
        return $this;
    }

    public function toBeInt(): static
    {
        if (!is_int($this->actual)) {
            throw new \RuntimeException("Esperava int, obteve " . gettype($this->actual));
        }
        return $this;
    }

    public function toBeArray(): static
    {
        if (!is_array($this->actual)) {
            throw new \RuntimeException("Esperava array, obteve " . gettype($this->actual));
        }
        return $this;
    }

    /** not->toBe(...), not->toBeNull(), etc. */
    public function not(): NotExpectation
    {
        return new NotExpectation($this->actual);
    }

    private function stringify(mixed $value): string
    {
        if (is_null($value))    return 'null';
        if (is_bool($value))    return $value ? 'true' : 'false';
        if (is_array($value))   return 'array(' . count($value) . ')';
        if (is_object($value))  return get_class($value);
        return (string) $value;
    }
}

class NotExpectation extends Expectation
{
    public function toBe(mixed $expected): static
    {
        if ($this->actual === $expected) {
            throw new \RuntimeException("Não esperava: " . json_encode($expected));
        }
        return $this;
    }

    public function toBeNull(): static
    {
        return $this->toBe(null);
    }
}
