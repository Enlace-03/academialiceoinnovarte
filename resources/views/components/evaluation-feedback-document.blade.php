{{--
    Documento de retroalimentación del docente (EvaluationAttachment). Sección
    aparte, junto al comentario de texto de la evaluación -- nunca dentro de
    la lista de adjuntos de la entrega (esos son evidencia del estudiante).
    La ruta autoriza con EvaluationAttachmentPolicy::view().
--}}
@props(['attachment' => null])

@if ($attachment)
    <p class="text-sm mt-2">
        <span class="text-gray-500">Documento del docente:</span>
        <a href="{{ route('evaluations.attachments.show', $attachment) }}" class="text-primary-600 hover:underline" rel="noopener noreferrer">
            📄 {{ $attachment->original_filename ?? 'Documento' }}
        </a>
    </p>
@endif
