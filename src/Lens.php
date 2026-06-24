<?php

namespace UltimateLemon\Lens;

class Lens
{
    protected static ?string $host = null;
    protected static ?int $port = null;
    protected static ?bool $enabled = null;
    protected static bool $queriesHooked = false;
    protected static array $hooked = [];

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

    public function red(): static { return $this->color('red'); }
    public function green(): static { return $this->color('green'); }
    public function blue(): static { return $this->color('blue'); }
    public function orange(): static { return $this->color('orange'); }
    public function purple(): static { return $this->color('purple'); }
    public function gray(): static { return $this->color('gray'); }

    public function values(mixed ...$values): static
    {
        $this->values = $values;
        return $this->transmitSelf();
    }

    public static function clear(): void
    {
        static::transmit(['id' => static::uuid(), 'type' => 'clear']);
    }

    /**
     * Pause execution until you click Continue or Stop in the Lens app.
     * Returns immediately if Lens is disabled or the app is not reachable.
     */
    public static function pause(): bool
    {
        if (! static::enabled()) {
            return true;
        }

        $id = static::uuid();
        static::transmit([
            'id'     => $id,
            'type'   => 'pause',
            'color'  => 'purple',
            'origin' => static::resolveOrigin(),
        ]);

        $deadline = time() + 300; // safety cap: 5 minutes
        while (time() < $deadline) {
            usleep(400000); // 0.4s
            [$reachable, $action] = static::fetchPauseAction($id);

            if (! $reachable) {
                return true; // app closed — don't hang
            }
            if ($action === 'continue') {
                return true;
            }
            if ($action === 'stop') {
                exit;
            }
        }

        return true;
    }

    protected static function fetchPauseAction(string $id): array
    {
        $url = sprintf('http://%s:%d/pause/%s', static::host(), static::port(), rawurlencode($id));

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER    => true,
            CURLOPT_TIMEOUT_MS        => 1500,
            CURLOPT_CONNECTTIMEOUT_MS => 500,
        ]);
        $res = curl_exec($ch);
        $errno = curl_errno($ch);
        curl_close($ch);

        if ($errno !== 0 || $res === false) {
            return [false, null];
        }

        $data = json_decode($res, true);
        return [true, $data['action'] ?? null];
    }

    /** Send a single database query to Lens. */
    public static function query(string $sql, array $bindings = [], ?float $time = null, ?string $connection = null): void
    {
        static::transmit([
            'id'    => static::uuid(),
            'type'  => 'query',
            'color' => 'blue',
            'query' => [
                'sql'        => $sql,
                'bindings'   => array_map(static fn ($b) => is_scalar($b) || $b === null ? $b : (string) $b, $bindings),
                'time'       => $time,
                'connection' => $connection,
            ],
        ]);
    }

    /** Stream every Laravel database query to Lens (Laravel only). */
    public static function showQueries(): void
    {
        if (static::$queriesHooked) {
            return;
        }
        if (! class_exists(\Illuminate\Support\Facades\DB::class)) {
            return;
        }

        static::$queriesHooked = true;

        \Illuminate\Support\Facades\DB::listen(static function ($query): void {
            static::query(
                $query->sql,
                $query->bindings ?? [],
                $query->time ?? null,
                $query->connectionName ?? null
            );
        });
    }

    /** Send outgoing mails (incl. a rendered HTML preview) to Lens (Laravel only). */
    public static function showMails(): void
    {
        static::hookOnce('mails', static function (): void {
            \Illuminate\Support\Facades\Event::listen(\Illuminate\Mail\Events\MessageSending::class, static function ($event): void {
                $message = $event->message;

                static::transmit([
                    'id'    => static::uuid(),
                    'type'  => 'mail',
                    'color' => 'blue',
                    'mail'  => [
                        'subject' => method_exists($message, 'getSubject') ? $message->getSubject() : null,
                        'to'      => static::addresses($message, 'getTo'),
                        'from'    => static::addresses($message, 'getFrom'),
                        'cc'      => static::addresses($message, 'getCc'),
                        'html'    => method_exists($message, 'getHtmlBody') ? static::stringifyBody($message->getHtmlBody()) : null,
                        'text'    => method_exists($message, 'getTextBody') ? static::stringifyBody($message->getTextBody()) : null,
                    ],
                ]);
            });
        });
    }

    protected static function stringifyBody($body): ?string
    {
        // Only forward string bodies — never consume a stream (that would break sending).
        return is_string($body) ? $body : null;
    }

    /** Send queue job lifecycle (processing/processed/failed) to Lens. */
    public static function showJobs(): void
    {
        static::hookOnce('jobs', static function (): void {
            $event = \Illuminate\Support\Facades\Event::class;

            $event::listen(\Illuminate\Queue\Events\JobProcessing::class, static fn ($e) => static::logPayload(
                [['job' => $e->job->resolveName(), 'connection' => $e->connectionName, 'status' => 'processing']], 'Job', 'orange'
            ));
            $event::listen(\Illuminate\Queue\Events\JobProcessed::class, static fn ($e) => static::logPayload(
                [['job' => $e->job->resolveName(), 'status' => 'processed']], 'Job', 'green'
            ));
            $event::listen(\Illuminate\Queue\Events\JobFailed::class, static fn ($e) => static::logPayload(
                [['job' => $e->job->resolveName(), 'status' => 'failed', 'error' => $e->exception->getMessage()]], 'Job', 'red'
            ));
        });
    }

    /** Send application events to Lens (skips framework + eloquent noise). */
    public static function showEvents(): void
    {
        static::hookOnce('events', static function (): void {
            \Illuminate\Support\Facades\Event::listen('*', static function ($eventName) : void {
                if (! is_string($eventName)) {
                    return;
                }
                if (str_starts_with($eventName, 'eloquent.') || str_starts_with($eventName, 'Illuminate\\')) {
                    return;
                }
                static::logPayload([['event' => $eventName]], 'Event', 'gray');
            });
        });
    }

    /** Send Eloquent model changes (created/updated/deleted/restored) to Lens. */
    public static function showModels(): void
    {
        static::hookOnce('models', static function (): void {
            \Illuminate\Support\Facades\Event::listen('eloquent.*', static function ($eventName, $models) : void {
                if (! is_string($eventName) || ! preg_match('/^eloquent\.(created|updated|deleted|restored): (.+)$/', $eventName, $m)) {
                    return;
                }
                $model = is_array($models) ? ($models[0] ?? null) : $models;
                $attributes = (is_object($model) && method_exists($model, 'getAttributes')) ? $model->getAttributes() : null;

                static::logPayload([['model' => $m[2], 'event' => $m[1], 'attributes' => $attributes]], 'Model', 'green');
            });
        });
    }

    /** Send notifications (any channel) to Lens (Laravel only). */
    public static function showNotifications(): void
    {
        static::hookOnce('notifications', static function (): void {
            \Illuminate\Support\Facades\Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, static function ($event): void {
                $notifiable = $event->notifiable;
                $who = is_object($notifiable)
                    ? get_class($notifiable) . (isset($notifiable->id) ? '#' . $notifiable->id : '')
                    : (string) $notifiable;

                static::logPayload([[
                    'notification' => get_class($event->notification),
                    'channel'      => $event->channel,
                    'notifiable'   => $who,
                ]], 'Notification', 'purple');
            });
        });
    }

    protected static function hookOnce(string $key, callable $register): void
    {
        if (isset(static::$hooked[$key]) || ! class_exists(\Illuminate\Support\Facades\Event::class)) {
            return;
        }
        static::$hooked[$key] = true;
        $register();
    }

    protected static function addresses($message, string $method): array
    {
        if (! method_exists($message, $method)) {
            return [];
        }
        $out = [];
        foreach ((array) $message->{$method}() as $address) {
            $out[] = is_object($address) && method_exists($address, 'getAddress') ? $address->getAddress() : (string) $address;
        }
        return $out;
    }

    protected static function logPayload(array $values, ?string $label, ?string $color): void
    {
        static::transmit([
            'id'     => static::uuid(),
            'type'   => 'log',
            'label'  => $label,
            'color'  => $color,
            'values' => $values,
        ]);
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
        $payload['meta'] = ['client' => 'php', 'version' => '1.1.0'];

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
