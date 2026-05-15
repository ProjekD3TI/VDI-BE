<x-mail::message>
    # Halo {{ $data['name'] }},

    Virtual Machine (VDI) Anda telah berhasil dibuat dan siap digunakan. Berikut adalah detail informasi akses Anda:

    <x-mail::panel>
        **Username:** {{ $data['username'] }}<br>
        **Password:** {{ $data['password'] }}<br>
        **IP Address VM:** {{ $data['ip_address'] }}
    </x-mail::panel>

    Anda dapat mengakses Virtual Machine melalui portal Guacamole kami. Gunakan kredensial di atas untuk login ke dalam
    sistem.

    <x-mail::button :url="env('GUACAMOLE_URL')">
        Login ke Portal VDI
    </x-mail::button>

    *Harap segera ubah password Anda setelah berhasil login pertama kali demi keamanan.*

    Terima kasih,<br>
    {{ config('app.name') }}
</x-mail::message>