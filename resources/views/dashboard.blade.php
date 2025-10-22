<!-- resources/views/dashboard.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@3.4.0/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 text-gray-800">
    <div class="max-w-2xl mx-auto mt-20 p-6 bg-white shadow-xl rounded-xl">
        <h1 class="text-2xl font-bold mb-2">Welcome, {{ Auth::user()->name }}</h1>
        <p class="mb-6 text-gray-600">You are successfully logged in via Keycloak SSO.</p>

        <div class="space-x-4">
            <a href="{{ route('dashboard') }}" class="text-blue-600 hover:underline">Dashboard</a>
            <form class="inline" method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="text-red-600 hover:underline">Logout</button>
            </form>
        </div>
    </div>

    <div class="mt-6">
    <button id="apiTest" class="bg-blue-600 text-white px-3 py-2 rounded hover:bg-blue-700">
        Uji Akses Protected API
    </button>
</div>

<script>
document.getElementById('apiTest').addEventListener('click', async () => {
    try {
        const token = "{{ session('kc_access_token') }}";
        const res = await fetch('http://localhost:8000/api/secure-data', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await res.json();
        alert(JSON.stringify(data, null, 2));
    } catch (err) {
        alert('Gagal memanggil API: ' + err);
    }
});
</script>

</body>
</html>
