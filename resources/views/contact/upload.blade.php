<form action="{{ route('contact.import-excel') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="excel_file" required>
    <button type="submit">Importar</button>
</form> 