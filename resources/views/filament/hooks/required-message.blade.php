{{-- Os balões de validação são nativos do navegador e seguem o idioma dele; aqui ficam sempre em português. --}}
<script>
    (() => {
        const clear = (field) => {
            if (field?.dataset?.nativeMessage) {
                field.setCustomValidity('');
                delete field.dataset.nativeMessage;
            }
        };

        const messageFor = (field) => {
            const { validity, type, min, max, minLength } = field;

            if (validity.valueMissing) return 'Preencha este campo.';
            if (validity.typeMismatch) return type === 'email' ? 'Informe um e-mail válido.' : type === 'url' ? 'Informe uma URL válida.' : 'Informe um valor válido.';
            if (validity.badInput) return type === 'number' ? 'Informe um número.' : 'Informe um valor válido.';
            if (validity.patternMismatch) return 'Use o formato solicitado.';
            if (validity.tooShort) return `Use pelo menos ${minLength} caracteres.`;
            if (validity.rangeUnderflow) return `O valor mínimo é ${min}.`;
            if (validity.rangeOverflow) return `O valor máximo é ${max}.`;
            if (validity.stepMismatch) return 'Informe um valor válido.';
        };

        document.addEventListener('invalid', ({ target }) => {
            // Uma mensagem própria já definida por outro código tem prioridade.
            const message = target.validity.customError ? null : messageFor(target);

            if (message) {
                target.setCustomValidity(message);
                target.dataset.nativeMessage = 'true';
            }
        }, true);

        document.addEventListener('input', ({ target }) => clear(target), true);
        document.addEventListener('change', ({ target }) => clear(target), true);

        // O Livewire pode preencher campos sem disparar eventos: limpa antes de o navegador validar de novo.
        document.addEventListener('click', ({ target }) => {
            const form = target.closest?.('button')?.form;
            Array.from(form?.elements ?? []).forEach(clear);
        }, true);
    })();
</script>
