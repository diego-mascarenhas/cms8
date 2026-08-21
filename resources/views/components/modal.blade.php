@props(['id', 'maxWidth', 'modal' => false])

@php
$id = $id ?? md5($attributes->wire('model'));

switch ($maxWidth ?? '') {
    case 'sm':
        $maxWidth = ' modal-sm';
        break;
    case 'md':
        $maxWidth = '';
        break;
    case 'lg':
        $maxWidth = ' modal-lg';
        break;
    case 'xl':
        $maxWidth = ' modal-xl';
        break;
    case '2xl':
    default:
        $maxWidth = '';
        break;
}
@endphp

<!-- Modal -->
<div
    x-data="{ show: @entangle($attributes->wire('model')) }"
    x-init="
        const el = $el;
        const instance = () => bootstrap.Modal.getOrCreateInstance(el);
        $watch('show', value => {
            if (value) {
                instance().show();
            } else {
                instance().hide();
            }
        });
        if (show) {
            instance().show();
        }
        el.addEventListener('hide.bs.modal', () => {
            show = false;
        });
    "
    wire:ignore.self
    class="modal fade"
    tabindex="-1"
    id="{{ $id }}"
    aria-labelledby="{{ $id }}"
    aria-hidden="true"
    x-ref="{{ $id }}"
>
  <div class="modal-dialog{{ $maxWidth }}">
    {{ $slot }}
  </div>
</div>
