<?php

namespace App\Entity\Log;

use App\Repository\Log\AppEventLogRepository;
use App\Entity\User\User;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AppEventLogRepository::class)]
#[ORM\Table(name: 'app_event_log')]
#[ORM\Index(name: 'IDX_event_type_created', columns: ['event_type', 'created_at'])]
#[ORM\Index(name: 'IDX_event_entity', columns: ['entity_type', 'entity_id'])]
#[ORM\Index(name: 'IDX_event_user_created', columns: ['user_id', 'created_at'])]
class AppEventLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?string $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_user', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(name: 'event_id', length: 36, nullable: true)]
    private ?string $eventId = null;

    #[ORM\Column(name: 'session_id', length: 128, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(name: 'request_id', length: 128, nullable: true)]
    private ?string $requestId = null;

    #[ORM\Column(name: 'trace_id', length: 64, nullable: true)]
    private ?string $traceId = null;

    #[ORM\Column(name: 'span_id', length: 32, nullable: true)]
    private ?string $spanId = null;

    #[ORM\Column(length: 50)]
    private ?string $event_type = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $action = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $outcome = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $message = null;

    #[ORM\Column(name: 'user_agent', length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'duration_ms', nullable: true)]
    private ?int $durationMs = null;

    #[ORM\Column(name: 'risk_score', type: 'decimal', precision: 5, scale: 2, nullable: true)]
    private ?string $riskScore = null;

    #[ORM\Column(name: 'anomaly_score', type: 'decimal', precision: 8, scale: 4, nullable: true)]
    private ?string $anomalyScore = null;

    #[ORM\Column(length: 50)]
    private ?string $entity_type = null;

    #[ORM\Column(nullable: true)]
    private ?int $entity_id = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(name: 'event_timestamp', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $eventTimestamp = null;

    #[ORM\Column(length: 20)]
    private string $level = 'INFO';

    #[ORM\Column(name: 'service_name', length: 100, nullable: true)]
    private ?string $serviceName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $environment = null;

    #[ORM\Column(name: 'application_version', length: 50, nullable: true)]
    private ?string $applicationVersion = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->eventTimestamp = new \DateTime();
        $this->eventId = self::uuid();
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getEventId(): ?string
    {
        return $this->eventId;
    }

    public function setEventId(?string $eventId): self
    {
        $this->eventId = $eventId;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): self
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    public function setRequestId(?string $requestId): self
    {
        $this->requestId = $requestId;
        return $this;
    }

    public function getTraceId(): ?string
    {
        return $this->traceId;
    }

    public function setTraceId(?string $traceId): self
    {
        $this->traceId = $traceId;
        return $this;
    }

    public function getSpanId(): ?string
    {
        return $this->spanId;
    }

    public function setSpanId(?string $spanId): self
    {
        $this->spanId = $spanId;
        return $this;
    }

    public function getEventType(): ?string
    {
        return $this->event_type;
    }

    public function setEventType(string $event_type): self
    {
        $this->event_type = $event_type;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    public function getOutcome(): ?string
    {
        return $this->outcome;
    }

    public function setOutcome(?string $outcome): self
    {
        $this->outcome = $outcome;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    public function getDurationMs(): ?int
    {
        return $this->durationMs;
    }

    public function setDurationMs(?int $durationMs): self
    {
        $this->durationMs = $durationMs;
        return $this;
    }

    public function getRiskScore(): ?string
    {
        return $this->riskScore;
    }

    public function setRiskScore(null|string|float|int $riskScore): self
    {
        $this->riskScore = $riskScore === null ? null : (string) $riskScore;
        return $this;
    }

    public function getAnomalyScore(): ?string
    {
        return $this->anomalyScore;
    }

    public function setAnomalyScore(null|string|float|int $anomalyScore): self
    {
        $this->anomalyScore = $anomalyScore === null ? null : (string) $anomalyScore;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entity_type;
    }

    public function setEntityType(string $entity_type): self
    {
        $this->entity_type = $entity_type;
        return $this;
    }

    public function getEntityId(): ?int
    {
        return $this->entity_id;
    }

    public function setEntityId(?int $entity_id): self
    {
        $this->entity_id = $entity_id;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getEventTimestamp(): ?\DateTimeInterface
    {
        return $this->eventTimestamp;
    }

    public function setEventTimestamp(?\DateTimeInterface $eventTimestamp): self
    {
        $this->eventTimestamp = $eventTimestamp;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(?string $level): self
    {
        $this->level = $level ?: 'INFO';
        return $this;
    }

    public function getServiceName(): ?string
    {
        return $this->serviceName;
    }

    public function setServiceName(?string $serviceName): self
    {
        $this->serviceName = $serviceName;
        return $this;
    }

    public function getEnvironment(): ?string
    {
        return $this->environment;
    }

    public function setEnvironment(?string $environment): self
    {
        $this->environment = $environment;
        return $this;
    }

    public function getApplicationVersion(): ?string
    {
        return $this->applicationVersion;
    }

    public function setApplicationVersion(?string $applicationVersion): self
    {
        $this->applicationVersion = $applicationVersion;
        return $this;
    }

    public function getDisplayMessage(): string
    {
        return $this->message
            ?: ($this->action ?: str_replace('_', ' ', (string) $this->event_type));
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
