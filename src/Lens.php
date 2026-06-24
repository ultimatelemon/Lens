<?php

namespace UltimateLemon\Lens;

class Lens
{
    protected static ?string $host = null;
    protected static ?int $port = null;
    protected static ?bool $enabled = null;

    protected string $id;
    protected array $values;
    protected ?string $label = null;
    protected ?string $color = null;
    protected array $origin = ['file' => null, 'line' => null];

    public function __construct(array $values = [])
    {
        $this->id = static::uuid();
        $this->values = $values;

        if (! static::enabled()) {
            return;
        }

        if (count($values) === 1 && $values[0] instanceof \Throwable) {
            static::exception($values[0]);
            return;
        }

        $this->origin = static::resolveOrigin();
        $this->transmitSelf();
    }

    public function label(string $label): static
    {
        $this->label = $label;
        return $this->transmitSelf();
    }

    public function color(string $color): static
    {
        $this->color = $color;
        return $this->transmitSelf();
    }

    public function values(mixed ...$values): static
    {
        $this->values = $values;
        return $this->transmitSelf();
    }

    public static function clear(): void
    {
        static::transmit(['id' => static::uuid(), 'type' => 'clear']);
    }

    /** Stuur een exception/throwable netjes weergegeven naar Lens. */
    public static function exception(\Throwable $e): void
    {
        static::transmit([
            'id'        => static::uuid(),
            'type'      => 'exception',
            'color'     => 'red',
            'origin'    => ['file' => $e->getFile(), 'line' => $e->getLine()],
            'exception' => [
                'class'   => get_class($e),
                'message' => $e->getMessage(),
                'code'    => $e->getCode(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
                'frames'  => static::framesFrom($e),
            ],
        ]);
    }

    protected static function framesFrom(\Throwable $e): array
    {
        $frames = [[
            'file'     => $e->getFile(),
            'line'     => $e->getLine(),
            'function' => null,
            'class'    => null,
            'type'     => null,
        ]];

        foreach ($e->getTrace() as $frame) {
            $frames[] = [
                'file'     => $frame['file'] ?? null,
                'line'     => $frame['line'] ?? null,
                'function' => $frame['function'] ?? null,
                'class'    => $frame['class'] ?? null,
                'type'     => $frame['type'] ?? null,
            ];

            if (count($frames) >= 30) {
                break;
            }
        }

        return $frames;
    }

    /** Stel host + poort handmatig in (overschrijft env). */
    public static function configure(string $host, int $port): void
    {
        static::$host = $host;
        static::$port = $port;
    }

    public static function enable(): void
    {
        static::$enabled = true;
    }

    public static function disable(): void
    {
        static::$enabled = false;
    }

    protected static function enabled(): bool
    {
        if (static::$enabled !== null) {
            return static::$enabled;
        }

        $env = getenv('LENS_ENABLED');
        if ($env !== false && $env !== '') {
            return static::$enabled = ! in_array(strtolower($env), ['0', 'false', 'off', 'no'], true);
        }

        return static::$enabled = true;
    }

    protected static function host(): string
    {
        if (static::$host !== null) {
            return static::$host;
        }

        $env = getenv('LENS_HOST');
        return ($env !== false && $env !== '') ? $env : '127.0.0.1';
    }

    protected static function port(): int
    {
        if (static::$port !== null) {
            return static::$port;
        }

        $env = getenv('LENS_PORT');
        return ($env !== false && $env !== '') ? (int) $env : 23600;
    }

    protected function transmitSelf(): static
    {
        static::transmit([
            'id'     => $this->id,
            'type'   => 'log',
            'label'  => $this->label,
            'color'  => $this->color,
            'origin' => $this->origin,
            'values' => $this->values,
        ]);

        return $this;
    }

    protected static function resolveOrigin(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 8);

        foreach ($trace as $frame) {
            if (! isset($frame['file'])) {
                continue;
            }
            if (str_contains($frame['file'], 'Lens.php') || str_contains($frame['file'], 'helpers.php')) {
                continue;
            }

            return ['file' => $frame['file'], 'line' => $frame['line'] ?? null];
        }

        return ['file' => null, 'line' => null];
    }

    protected static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    protected static function transmit(array $payload): void
    {
        if (! static::enabled()) {
            return;
        }

        $payload['time'] = $payload['time'] ?? (int) round(microtime(true) * 1000);
        $payload['meta'] = ['client' => 'php', 'version' => '1.0.1'];

        $json = json_encode($payload, JSON_PARTIAL_OUTPUT_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            return;
        }

        $url = sprintf('http://%s:%d', static::host(), static::port());

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST              => true,
            CURLOPT_POSTFIELDS        => $json,
            CURLOPT_HTTPHEADER        => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => 1000,
            CURLOPT_CONNECTTIMEOUT_MS => 300,
        ]);
        curl_exec($ch);
        curl_close($ch);
        // Fouten worden bewust genegeerd: debuggen mag je app nooit breken.
    }
}
