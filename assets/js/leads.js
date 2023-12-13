(function($) {
"use strict";
  
  if ($("#customerLeadsForm").length > 0) {
      $("#customerLeadsForm").validate();
  }
  $(document).on('submit', '#customerLeadsForm', function (e) {
    e.preventDefault();

    var form = $(this);

    // Validate form fields
    if (!form.valid()) return false;

    // Collect form data using FormData
    var getData = form.serializeArray();
    
    var fileInput = $('#fileuploadfield')[0];
    var fileData;

    if (fileInput && fileInput.files && fileInput.files.length > 0) {
        fileData = fileInput.files[0];
    }

    // console.log(fileData);

    var data = new FormData();

    data.append("logoFile", fileData);
    data.append("nonce", leads_object.nonce);
    data.append("action", 'ml_leads_entry');

    // Append each form field to the FormData object
    $.each(getData, function (index, field) {
        data.append(field.name, field.value);
    });

    // console.log(data);

    form.find('button.ml_add_loading').addClass('ml_loading');

    // Send data to webhook
    $.ajax({
        type: 'POST',
        url: leads_object.ajax_url,
        data: data,
        dataType: "json",
        contentType: false,
        processData: false,
        success: function (response, textStatus, jqXHR) {
            form.find('button.ml_add_loading').removeClass('ml_loading');

            if( response.data && response.data.message ) {
              $('#formResponse').text(response.data.message);
            }

            if( response.success === true) {
              $('#customerLeadsForm')[0].reset();
            }
        },
        error: function (error) {
            $('#formResponse').html('Error submitting form: ' + error.responseText);
        }
    });
});
  

})(jQuery);