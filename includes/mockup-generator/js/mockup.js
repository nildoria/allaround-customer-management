;(function ($) {
"use strict";

// add generate button after "Add New" button on user edit page
function addGenerateButton() {
    // Find the "Add New" button by its CSS class
    var addButton = $('.page-title-action');

    // Retrieve the User ID from the URL
    var user_id = getParameterByName('user_id'); // You need to define the getParameterByName function

    if( user_id ) {
        $.ajax({
            type: 'POST',
            url: mockupGeneratorAjax.ajax_url,
            data: {
                action: 'get_generate_button', // AJAX action to check progress
                user_id: user_id,
                nonce: mockupGeneratorAjax.nonce
            },
            dataType: 'html',
            success: function(response) {
                if( response) {    
                    // Insert the custom button before the "Add New" button
                    addButton.after(response);
                }
            }
        });
    }
    

   
}

addGenerateButton();

// Function to get a query parameter from the URL
function getParameterByName(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    var results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// Function to poll and update progress
function pollProgress(user_id, current) {

    checkProress(user_id, intervalId, current);

    // Interval in milliseconds for polling (adjust as needed)
    var pollingInterval = 5000; // Poll every 1 second

    // Set up a recurring AJAX request
    var intervalId = setInterval(function() {
        checkProress(user_id, intervalId, current);
    }, pollingInterval);
}



function checkProress(user_id, intervalId, current) {
    $.ajax({
        type: 'POST',
        url: mockupGeneratorAjax.ajax_url,
        data: {
            action: 'mockup_generation_progress', // AJAX action to check progress
            user_id: user_id,
            nonce: mockupGeneratorAjax.nonce
        },
        dataType: 'json',
        success: function(response) {
            if (response.progress === 'completed') {
                clearInterval(intervalId); // Stop polling when the task is completed
                current.removeClass('ml_loading').prop('disabled', false);
                current.closest('.alarnd--mockup-trigger-area').find('.ml_mockup_progress_bar').text('100');
            } else if (response.progress === 'in-progress') {
                current.prop('disabled', true);
            } else {
                var numericProgress = parseInt(response.progress);
                numericProgress = numericProgress < 0 ? 0 : numericProgress;
                console.log(numericProgress);
                current.closest('.alarnd--mockup-trigger-area').find('.ml_mockup_progress_bar').text(numericProgress);
            }
        },
    });
}


// $( document ).on('click', '.ml_mockup_gen_trigger', function() {

//     var current = $(this),
//         user_id = current.data('user_id');

//     current.closest('.alarnd--mockup-trigger-area').find('.ml_mockup_progress_bar').text('0');
//     current.addClass('ml_loading').prop("disabled", true);

//     $.ajax({
//         url: mockupGeneratorAjax.ajax_url,
//         type: 'POST',
//         dataType: 'html',
//         data: {
//             action: 'generate_mockup',
//             "user_id": user_id,
//             nonce: mockupGeneratorAjax.nonce
//         },
//         success: function(response) {
//             // Handle the AJAX response (e.g., start polling for progress)
//             if (response === 'Background process scheduled.') {
//                 pollProgress(user_id, current); // Replace with the actual user ID
//             } else {
//                 current.removeClass('ml_loading');
//             }
//         }
//     });
// });









})(jQuery);

// Initialize the task queue from local storage on page load
let taskQueue = [];

// Check if local storage has saved tasks and load them
if (localStorage.getItem('taskQueue')) {
  taskQueue = JSON.parse(localStorage.getItem('taskQueue'));
}

// Create a variable to store the Web Worker instance.
let worker = new Worker(mockupGeneratorAjax.generate_file); // Path to the Web Worker script
let isWorkerBusy = false;

// Function to add a task to the queue.
function addToQueue(type, backgrounds, logo, logo_second, user_id, logoData, logo_type) {
  const task = { type, backgrounds, logo, logo_second, user_id, logoData, logo_type };

  taskQueue.push(task);

  // Save the task queue to local storage
  localStorage.setItem('taskQueue', JSON.stringify(taskQueue));

  // If the worker is not busy, start processing tasks.
  if (!isWorkerBusy) {
    processQueue();
  }
}

// Function to process tasks from the queue.
function processQueue() {
  if (taskQueue.length > 0) {
    isWorkerBusy = true;
    const task = taskQueue[0]; // Get the first task from the queue

    // Send the task to the Web Worker.
    worker.postMessage(task);

    // Listen for the worker's response.
    worker.addEventListener('message', function (e) {
      if (e.data.type === 'progress') {
        // Handle progress updates here
        const progress = e.data.progress;
        const user_id = e.data.user_id;
        updateProgressBar(progress, user_id);
      } else if (e.data.type === 'imageGenerated') {
        // Get the imageData from the message
        const imageData = e.data.imageData;
        const filename = e.data.filename;
        const is_feature_image = e.data.is_feature_image;
        const user_id = e.data.user_id;

        // Create a new Canvas element
        const canvas = document.createElement('canvas');
        canvas.width = imageData.width;
        canvas.height = imageData.height;
        const ctx = canvas.getContext('2d');

        // Draw the image data onto the canvas
        ctx.putImageData(imageData, 0, 0);

        // Convert the canvas to a data URL (e.g., PNG format)
        const dataURL = canvas.toDataURL('image/png'); // You can change the format to 'image/jpeg' or others if needed

        console.log("user_id", user_id);
        console.log("is_feature_image from mockupjs", is_feature_image);

        // Send the dataURL to your server to save the image
        saveImageToServer(dataURL, filename, user_id, is_feature_image);
      }

      // Remove the completed task from the queue
      taskQueue.shift();

      // Save the updated task queue to local storage
      localStorage.setItem('taskQueue', JSON.stringify(taskQueue));

      // Continue processing the queue.
      isWorkerBusy = false;
      processQueue();
    });
  }
}

// Function to send the dataURL to the server
function saveImageToServer(dataURL, filename, user_id, is_feature_image) {
  // You can use AJAX or Fetch to send the dataURL to your server
  // Here's an example using Fetch:

  // Create a new Headers object and set the custom header

  fetch(mockupGeneratorAjax.image_save_endpoint, {
    method: 'POST',
    body: JSON.stringify({ imageData: dataURL, filename, user_id, is_feature_image }),
    headers: {
      'Content-Type': 'application/json'
    }
  })
    .then(response => {
      if (response.ok) {
        // Image was successfully saved on the server
        console.log('Image saved on the server');
      } else {
        // Handle the error if the save operation fails
        console.error('Failed to save image on the server');
      }
    })
    .catch(error => {
      console.error('Error sending data to the server:', error);
    });
}

function convertBackgrounds(images) {
  let backgrounds = [];

  for (let key in images) {
    if (images.hasOwnProperty(key)) {
      backgrounds.push({
        id: key,
        url: images[key]['thumbnail'][0],
        galleries: images[key]['galleries']
      });
    }
  }

  return backgrounds;
}

function convertLogos(logos) {
  let backgrounds = [];

  for (let key in logos) {
    if (logos.hasOwnProperty(key)) {
      // If the value is an array, iterate through its elements
      if (Array.isArray(logos[key])) {
        logos[key].forEach((item, index) => {
          backgrounds.push({
            product_id: parseInt(key),
            meta_key: item['meta_key'],
            meta_value: item['meta_value']
          });
        });
      } else {
        backgrounds.push({
          id: key,
          url: logos[key][0]
        });
      }
    }
  }

  return backgrounds;
}

// Function to update the progress bar
function updateProgressBar(progress, user_id) {
  const progressBar = document.getElementById('ml_mockup_progress_bar-'+user_id);
  const triggerBtn = document.getElementById('ml_mockup_gen-'+user_id);
  // progressBar.style.width = progress + '%';
  progressBar.textContent = progress;

  // You can also add additional logic to hide or reset the progress bar when the progress is 100%
  if (progress === 100) {
    triggerBtn.classList.remove("ml_loading");
  }
}

function trigger_generate(event) {
  // Get the attributes from the clicked button
  let settings = event.target.getAttribute('data-settings');

  if (settings.length === 0)
    return false;

  settings = JSON.parse(settings);

  if (
    !settings.images ||
    settings.images.length === 0 ||
    !settings.logo ||
    !settings.user_id
  ) {
    console.log("required variables are undefined");
    return false;
  }

  console.log("settings", settings);

  const backgrounds = convertBackgrounds(settings.images);
  console.log("settings-backgrounds", backgrounds);
  let logoData = '';
  if (settings.logo_positions && settings.logo_positions.length !== 0) {
    logoData = convertLogos(settings.logo_positions);
  }

  let logo_type = settings.logo_type;

  const logo = settings.logo;
  const logo_second = settings.logo_second;
  const user_id = settings.user_id;
  const type = 'generateImages';

  event.target.classList.add('ml_loading');

  // Pass the attributes to the sendTaskToWorker function
  addToQueue(type, backgrounds, logo, logo_second, user_id, logoData, logo_type);
}


document.addEventListener('DOMContentLoaded', function () {

  // Clear the task queue and local storage on page load
  taskQueue = [];
  localStorage.removeItem('taskQueue');

  document.addEventListener('click', function (event) {
    if (event.target && event.target.classList.contains('ml_mockup_gen_trigger')) {
      trigger_generate(event);
    }
});

const doactionBtn = document.getElementById('doaction');
if( doactionBtn ) {
  doactionBtn.addEventListener("click", function (e) {

    const action = document.getElementById("bulk-action-selector-top").value;
    if (action === 'alaround_mockup_gen') {
      e.preventDefault();
      const checkboxes = document.querySelectorAll('input[name="users[]"]:checked');
      checkboxes.forEach(function (checkbox) {
        if ("customer" === checkbox.classList.value) {

          const user_row = document.querySelector('tr#user-' + checkbox.value);
          if (user_row) {
            // Find a the trigger button from user row
            var triggerBtn = user_row.querySelector('.ml_mockup_gen_trigger');

            if (triggerBtn) {
              triggerBtn.click();
            } else {
              checkbox.checked = false;
            }
          } else {
            checkbox.checked = false;
          }
        } else {
          checkbox.checked = false;
        }
      });
    }
  });
}

});