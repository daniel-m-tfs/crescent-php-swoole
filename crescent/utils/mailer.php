<?php

namespace Crescent\Utils;

/**
 * Mailer — envio de e-mail via SMTP sem dependências externas.
 *
 * Suporta:
 *   - STARTTLS (porta 587)  ← recomendado
 *   - SSL/TLS   (porta 465)
 *   - Sem criptografia (porta 25 — não recomendado)
 *   - AUTH LOGIN e AUTH PLAIN
 *   - Corpos HTML + texto alternativo
 *   - Múltiplos destinatários (To, CC, BCC)
 *   - Anexos
 *
 * Configuração via .env:
 *   MAIL_DRIVER=smtp           # smtp | mail (fallback)
 *   MAIL_HOST=smtp.gmail.com
 *   MAIL_PORT=587
 *   MAIL_USER=seu@email.com
 *   MAIL_PASS=senha_de_app
 *   MAIL_FROM=noreply@meusite.com
 *   MAIL_FROM_NAME="Meu App"
 *   MAIL_ENCRYPTION=tls        # tls (STARTTLS) | ssl | none
 *
 * Uso:
 *   Mailer::to('ana@email.com', 'Ana')
 *         ->subject('Bem-vinda!')
 *         ->html('<h1>Olá, Ana!</h1>')
 *         ->text('Olá, Ana!')
 *         ->send();
 *
 *   // Múltiplos destinatários
 *   Mailer::to(['a@x.com', 'b@x.com'])
 *         ->cc('gerente@x.com')
 *         ->subject('Relatório')
 *         ->html($html)
 *         ->send();
 */
class Mailer
{
    private array  $to      = [];
    private array  $cc      = [];
    private array  $bcc     = [];
    private string $subject = '(sem assunto)';
    private string $html    = '';
    private string $text    = '';
    private array  $attachments = [];
    private array  $extraHeaders = [];

    // Configuração resolvida
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $from;
    private string $fromName;
    private string $encryption; // tls | ssl | none
    private string $driver;

    // ─── Factory ──────────────────────────────────────────────────────────────

    public static function to(string|array $address, string $name = ''): static
    {
        $instance = new static();
        $instance->loadConfig();

        if (is_array($address)) {
            foreach ($address as $addr) {
                $instance->to[] = static::formatAddress(
                    is_array($addr) ? ($addr[0] ?? '') : $addr,
                    is_array($addr) ? ($addr[1] ?? '') : ''
                );
            }
        } else {
            $instance->to[] = static::formatAddress($address, $name);
        }

        return $instance;
    }

    // ─── Fluent API ───────────────────────────────────────────────────────────

    public function cc(string $address, string $name = ''): static
    {
        $this->cc[] = static::formatAddress($address, $name);
        return $this;
    }

    public function bcc(string $address, string $name = ''): static
    {
        $this->bcc[] = static::formatAddress($address, $name);
        return $this;
    }

    public function subject(string $subject): static
    {
        $this->subject = $subject;
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    /**
     * Adiciona um anexo.
     *
     * @param string $path     Caminho absoluto do arquivo
     * @param string $name     Nome do arquivo no e-mail (opcional)
     * @param string $mimeType MIME type (opcional, detectado automaticamente)
     */
    public function attach(string $path, string $name = '', string $mimeType = ''): static
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Arquivo não encontrado para anexo: {$path}");
        }

        $this->attachments[] = [
            'path'     => $path,
            'name'     => $name ?: basename($path),
            'mimeType' => $mimeType ?: (mime_content_type($path) ?: 'application/octet-stream'),
        ];

        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->extraHeaders[$name] = $value;
        return $this;
    }

    // ─── Envio ────────────────────────────────────────────────────────────────

    /**
     * Envia o e-mail. Lança \RuntimeException em caso de falha.
     */
    public function send(): bool
    {
        if (empty($this->to)) {
            throw new \RuntimeException('Nenhum destinatário definido.');
        }

        if ($this->driver === 'mail') {
            return $this->sendWithMailFunction();
        }

        return $this->sendWithSmtp();
    }

    // ─── SMTP ─────────────────────────────────────────────────────────────────

    private function sendWithSmtp(): bool
    {
        $boundary = '=_' . bin2hex(random_bytes(16));
        $body     = $this->buildBody($boundary);
        $headers  = $this->buildHeaders($boundary);

        // Escolhe o socket correto
        if ($this->encryption === 'ssl') {
            $socket = $this->connect("ssl://{$this->host}", $this->port);
        } else {
            $socket = $this->connect($this->host, $this->port);
        }

        try {
            $this->expect($socket, '220');

            // EHLO
            $this->send_($socket, "EHLO " . gethostname());
            $ehlo = $this->read($socket);
            if (!str_contains($ehlo, '250')) {
                throw new \RuntimeException("EHLO falhou: {$ehlo}");
            }

            // STARTTLS
            if ($this->encryption === 'tls') {
                $this->send_($socket, 'STARTTLS');
                $this->expect($socket, '220');

                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new \RuntimeException('Falha ao habilitar TLS');
                }

                // Re-EHLO após TLS
                $this->send_($socket, "EHLO " . gethostname());
                $this->read($socket);
            }

            // AUTH
            if ($this->user) {
                $this->authenticate($socket, $ehlo);
            }

            // MAIL FROM
            $this->send_($socket, "MAIL FROM:<{$this->from}>");
            $this->expect($socket, '250');

            // RCPT TO
            $allRecipients = array_merge($this->to, $this->cc, $this->bcc);
            foreach ($allRecipients as $recipient) {
                preg_match('/<([^>]+)>/', $recipient, $m);
                $addr = $m[1] ?? $recipient;
                $this->send_($socket, "RCPT TO:<{$addr}>");
                $this->expect($socket, '25');
            }

            // DATA
            $this->send_($socket, 'DATA');
            $this->expect($socket, '354');

            $this->send_($socket, $headers . "\r\n" . $body . "\r\n.");
            $this->expect($socket, '250');

            $this->send_($socket, 'QUIT');
        } finally {
            fclose($socket);
        }

        return true;
    }

    private function authenticate($socket, string $ehlo): void
    {
        // Detecta mecanismos suportados
        $supportsPlain = str_contains($ehlo, 'AUTH') && str_contains($ehlo, 'PLAIN');

        if ($supportsPlain) {
            $credentials = base64_encode("\0{$this->user}\0{$this->pass}");
            $this->send_($socket, "AUTH PLAIN {$credentials}");
        } else {
            // AUTH LOGIN
            $this->send_($socket, 'AUTH LOGIN');
            $this->expect($socket, '334');
            $this->send_($socket, base64_encode($this->user));
            $this->expect($socket, '334');
            $this->send_($socket, base64_encode($this->pass));
        }

        $response = $this->read($socket);
        if (!str_starts_with($response, '235')) {
            throw new \RuntimeException("Autenticação SMTP falhou: {$response}");
        }
    }

    /** @return resource */
    private function connect(string $host, int $port)
    {
        $timeout = 15;
        $socket  = @stream_socket_client(
            "{$host}:{$port}",
            $errno,
            $errstr,
            $timeout,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            throw new \RuntimeException("Não foi possível conectar ao SMTP {$host}:{$port} — {$errstr} ({$errno})");
        }

        stream_set_timeout($socket, $timeout);

        return $socket;
    }

    /** @param resource $socket */
    private function send_($socket, string $data): void
    {
        fwrite($socket, $data . "\r\n");
    }

    /** @param resource $socket */
    private function read($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            // Linha de continuação tem '-' na posição 3: "250-Example"
            if (isset($line[3]) && $line[3] !== '-') {
                break;
            }
        }
        return $response;
    }

    /** @param resource $socket */
    private function expect($socket, string $code): void
    {
        $response = $this->read($socket);
        if (!str_starts_with(trim($response), $code)) {
            throw new \RuntimeException("SMTP esperava {$code}, obteve: " . trim($response));
        }
    }

    // ─── Fallback mail() ──────────────────────────────────────────────────────

    private function sendWithMailFunction(): bool
    {
        $boundary = '=_' . bin2hex(random_bytes(16));
        $body     = $this->buildBody($boundary);
        $headers  = $this->buildHeaders($boundary);

        $to      = implode(', ', $this->to);
        $subject = $this->encodeHeader($this->subject);

        return mail($to, $subject, $body, $headers);
    }

    // ─── Helpers de construção ────────────────────────────────────────────────

    private function buildHeaders(string $boundary): string
    {
        $fromFormatted = $this->fromName
            ? $this->encodeHeader($this->fromName) . " <{$this->from}>"
            : $this->from;

        $headers  = "From: {$fromFormatted}\r\n";
        $headers .= "To: " . implode(', ', $this->to) . "\r\n";

        if ($this->cc) {
            $headers .= "Cc: " . implode(', ', $this->cc) . "\r\n";
        }

        $headers .= "Subject: " . $this->encodeHeader($this->subject) . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "Message-ID: <" . microtime(true) . "@" . gethostname() . ">\r\n";
        $headers .= "X-Mailer: CrescentPHP-Mailer\r\n";

        foreach ($this->extraHeaders as $name => $value) {
            $headers .= "{$name}: {$value}\r\n";
        }

        if ($this->attachments) {
            $headers .= "Content-Type: multipart/mixed; boundary=\"{$boundary}\"\r\n";
        } elseif ($this->html && $this->text) {
            $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        } elseif ($this->html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: base64\r\n";
        }

        return $headers;
    }

    private function buildBody(string $boundary): string
    {
        // Sem multipart (somente texto ou somente HTML)
        if (!$this->attachments && !($this->html && $this->text)) {
            $content = $this->html ?: $this->text;
            return chunk_split(base64_encode($content));
        }

        $parts = '';

        // Parte alternativa (text + html)
        if ($this->html && $this->text) {
            $altBoundary = '=_alt_' . bin2hex(random_bytes(8));
            $parts .= "--{$boundary}\r\n";
            $parts .= "Content-Type: multipart/alternative; boundary=\"{$altBoundary}\"\r\n\r\n";
            $parts .= "--{$altBoundary}\r\n";
            $parts .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $parts .= chunk_split(base64_encode($this->text)) . "\r\n";
            $parts .= "--{$altBoundary}\r\n";
            $parts .= "Content-Type: text/html; charset=UTF-8\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $parts .= chunk_split(base64_encode($this->html)) . "\r\n";
            $parts .= "--{$altBoundary}--\r\n";
        } elseif ($this->html) {
            $parts .= "--{$boundary}\r\n";
            $parts .= "Content-Type: text/html; charset=UTF-8\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $parts .= chunk_split(base64_encode($this->html)) . "\r\n";
        } else {
            $parts .= "--{$boundary}\r\n";
            $parts .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n\r\n";
            $parts .= chunk_split(base64_encode($this->text)) . "\r\n";
        }

        // Anexos
        foreach ($this->attachments as $att) {
            $data = file_get_contents($att['path']);
            $name = $this->encodeHeader($att['name']);

            $parts .= "--{$boundary}\r\n";
            $parts .= "Content-Type: {$att['mimeType']}; name=\"{$name}\"\r\n";
            $parts .= "Content-Transfer-Encoding: base64\r\n";
            $parts .= "Content-Disposition: attachment; filename=\"{$name}\"\r\n\r\n";
            $parts .= chunk_split(base64_encode($data)) . "\r\n";
        }

        $parts .= "--{$boundary}--";

        return $parts;
    }

    // ─── Config ───────────────────────────────────────────────────────────────

    private function loadConfig(): void
    {
        $this->driver     = Env::get('MAIL_DRIVER',    'smtp');
        $this->host       = Env::get('MAIL_HOST',      'localhost');
        $this->port       = (int) Env::get('MAIL_PORT', '587');
        $this->user       = Env::get('MAIL_USER',      '');
        $this->pass       = Env::get('MAIL_PASS',      '');
        $this->from       = Env::get('MAIL_FROM',      Env::get('MAIL_USER', 'noreply@localhost'));
        $this->fromName   = Env::get('MAIL_FROM_NAME', Env::get('APP_NAME',  'CrescentPHP'));
        $this->encryption = strtolower(Env::get('MAIL_ENCRYPTION', $this->port === 465 ? 'ssl' : 'tls'));
    }

    // ─── Formatação ───────────────────────────────────────────────────────────

    private static function formatAddress(string $email, string $name = ''): string
    {
        $email = trim($email);
        $name  = trim($name);

        if ($name) {
            return "=?UTF-8?B?" . base64_encode($name) . "?= <{$email}>";
        }

        return $email;
    }

    private function encodeHeader(string $value): string
    {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }
}
