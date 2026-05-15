$('#category_id').on('change', function() {
    let categoryId = $(this).val();
    $.ajax({
        url: 'get_attribute_values.php',
        method: 'GET',
        data: { category_id: categoryId },
        success: function(response) {
            let attributes = JSON.parse(response);
            let html = '';
            attributes.forEach(attr => {
                html += `<div class="form-group">
                            <label>${attr.name}</label>
                            <input type="${attr.type}" name="attr[${attr.id}]" class="form-control">
                         </div>`;
            });
            $('#attributes-container').html(html);
        }
    });
});