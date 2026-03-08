@props([
    'length' => 6,
    'separator' => false,
])

<div
    {{ $attributes->merge(['class' => 's-otp']) }}
    x-data="{
        digits: Array({{ $length }}).fill(''),
        length: {{ $length }},
        separator: @js($separator),
        focus(index) {
            this.$refs['digit' + index]?.focus();
        },
        handleInput(index, event) {
            const val = event.target.value.replace(/\D/g, '');
            this.digits[index] = val.charAt(0) || '';
            event.target.value = this.digits[index];
            if (this.digits[index] && index < this.length - 1) {
                this.focus(index + 1);
            }
            this.checkComplete();
        },
        handleKeydown(index, event) {
            if (event.key === 'Backspace' && !this.digits[index] && index > 0) {
                this.focus(index - 1);
            }
        },
        handlePaste(event) {
            event.preventDefault();
            const text = (event.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, this.length);
            for (let i = 0; i < text.length; i++) {
                this.digits[i] = text[i];
                if (this.$refs['digit' + i]) this.$refs['digit' + i].value = text[i];
            }
            this.focus(Math.min(text.length, this.length - 1));
            this.checkComplete();
        },
        checkComplete() {
            const code = this.digits.join('');
            if (code.length === this.length && this.digits.every(d => d !== '')) {
                this.$dispatch('otp-complete', { code });
            }
        },
    }"
>
    @for($i = 0; $i < $length; $i++)
        @if($separator && $i === 3)
            <span class="s-otp-separator">&mdash;</span>
        @endif
        <input
            type="text"
            inputmode="numeric"
            maxlength="1"
            class="s-otp-digit"
            x-ref="digit{{ $i }}"
            x-on:input="handleInput({{ $i }}, $event)"
            x-on:keydown="handleKeydown({{ $i }}, $event)"
            x-on:paste="handlePaste($event)"
            x-bind:class="{ 'filled': digits[{{ $i }}] !== '' }"
            autocomplete="off"
        >
    @endfor
</div>
