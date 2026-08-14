{{--
    Solo lectura, usado dentro del Placeholder de evaluateSubmissionsAction()
    (ExpectedEvidencesRelationManager). Mismo criterio que la vista de
    solo lectura de EvidenceShow: isImage() decide <img> vs. ícono genérico
    + enlace de descarga para adjuntos type=photo (un adjunto migrado del
    esquema viejo, Hito 2, pudo no ser una imagen real) -- ver
    SubmissionAttachment::isImage() y TODO.md #6 en el docblock de la
    migración 2027_01_01_000400.
--}}
@if ($attachments->isEmpty())
    <p class="text-sm text-gray-400">Sin adjuntos.</p>
@else
    <div class="space-y-2">
        @foreach ($attachments as $attachment)
            <div class="text-sm">
                @if ($attachment->type === 'photo')
                    @if ($attachment->isImage())
                        <a href="{{ route('submissions.attachments.show', $attachment) }}" target="_blank">
                            <img src="{{ route('submissions.attachments.show', $attachment) }}" alt="{{ $attachment->original_filename }}" class="max-h-32 rounded inline-block">
                        </a>
                    @else
                        <a href="{{ route('submissions.attachments.show', $attachment) }}" target="_blank" class="text-primary-600 hover:underline">
                            📎 {{ $attachment->original_filename ?? 'Archivo' }}
                        </a>
                    @endif
                @else
                    <x-youtube-embed :url="$attachment->url" />
                @endif
            </div>
        @endforeach
    </div>
@endif
