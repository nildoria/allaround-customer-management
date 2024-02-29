;(function ($) {
"use strict";



/**
 * Image Generation System
 */

const itemPushEachAtOnce = 20;
let imageResultList = [];
let isGeneratingImages = false; // Flag to track whether image generation is in progress
const userQueue = []; // Queue to store users for processing

// Define a variable to control logging
let enableLogging = true;
const enableBackgroundColor = mockupGeneratorAjax?.background_enabled;

// Custom logging function
const customLog = (...args) => {
    if (enableLogging) {
        console.log(...args);
    }
};
    
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

function convertGallery(images) {
    let gallery = [];
    
    for (let key in images) {
        if (images.hasOwnProperty(key)) {
        gallery.push({
            id: key,
            attachment_id: images[key]['attachment_id'],
            url: images[key]['thumbnail'],
            type: images[key]['type']
        });
        }
    }
    
    return gallery;
}

function aspect_height(originalWidth, originalHeight, newWidth) {
    // Calculate the aspect ratio
    const aspectRatio = originalWidth / originalHeight;

    // Calculate the new height based on the aspect ratio
    const newHeight = newWidth / aspectRatio;

    return newHeight;
}

function aspectY(newHeight, height, y) {
    const newY = height > newHeight ? y + (height - newHeight) : y - ((newHeight - height)/2);
    return newY;
}


function getFileExtensionFromUrl(url) {
    // Use a regular expression to extract the file extension
    const regex = /(?:\.([^.]+))?$/; // Match the last dot and anything after it
    const extension = regex.exec(url)[1]; // Extract the extension (group 1 in the regex)

    // Ensure the extension is in lowercase (optional)
    if (extension) {
        return extension.toLowerCase();
    } else {
        return null; // Return null if no extension is found
    }
}

// Function to generate an image with logos
const generateImageWithLogos = async (backgroundUrl, user_id, product_id, logo, logo_second, custom_logo, logoData, logo_type, custom_logo_type, gallery = false) => {

    let itemResult = []

    // Extract the filename from the background URL
    const file_ext = getFileExtensionFromUrl(backgroundUrl);
    let filename = product_id + '.' + file_ext;
    let is_feature_image = false === gallery ? true : false;

    //customLog("gallery", gallery);
    if( gallery && gallery !== false && gallery.length !== 0 ) {
        filename = product_id + '-' + gallery['id'] + '-' + gallery['attachment_id'] + '.' + file_ext;
    }
    //customLog("filename", filename);

    // Load background image as Blob
    const backgroundResponse = await fetch(backgroundUrl);
    // //customLog( backgroundUrl, product_id );
    // //customLog( backgroundResponse );
    if (!backgroundResponse.ok) {
        throw new Error(`Failed to fetch background image: ${backgroundResponse.status} ${backgroundResponse.statusText}`);
    }
    const backgroundBlob = await backgroundResponse.blob();
    const backgroundImage = await createImageBitmap(backgroundBlob);

    // Create a canvas element to work with
    const staticCanvas = new OffscreenCanvas(backgroundImage.width, backgroundImage.height);
    const ctx = staticCanvas.getContext('2d');

    // Draw the background image
    ctx.drawImage(backgroundImage, 0, 0);

    // Use Array.filter() to get items with the matching product_id
    const itemsWithMatchingProductID = logoData.filter(item => item.product_id == product_id);

    // //customLog( 'itemsWithMatchingProductID', itemsWithMatchingProductID );

    // Find an item with the matching meta_key "ml_logos_positions_{user_id}"
    const matchingItem = itemsWithMatchingProductID.find(item => item.meta_key === `ml_logos_positions_${user_id}`);

    // //customLog( 'matchingItem', matchingItem );

    // If found, use it; otherwise, fall back to "ml_logos_positions"
    const resultItem = matchingItem || itemsWithMatchingProductID.find(item => item.meta_key === "ml_logos_positions");

    // //customLog( 'resultItem', resultItem );

    // customLog(`====> is_feature:${is_feature_image} id:${product_id} user:${user_id} logo_type:${logo_type} custom_logo_type:${custom_logo_type}`, resultItem);

    if (resultItem != undefined) {
        
        let finalItem = resultItem.meta_value[logo_type];
        let logoNumber = resultItem.meta_value['logoNumber'];
            logoNumber = logoNumber !== undefined ? logoNumber : 'default';
        
        customLog("logo_type", logo_type, finalItem);

        // check if select second logo or not
        // check if second logo value exists or not
        let finalLogo = logo;
        let finalLogoNumber = 'lighter';

        if(logoNumber === 'second' && (logo_second && logo_second != null && logo_second != undefined)) {
            finalLogo = logo_second;
            finalLogoNumber = 'darker';
        }

        if( gallery && gallery !== false && gallery.length !== 0 ) {
            
            if( gallery['type'] == 'light' ) {
                finalLogo = logo;
                finalLogoNumber = 'lighter';
            }
            if( gallery['type'] == 'dark' && (logo_second && logo_second != null && logo_second != undefined) ) {
                finalLogo = logo_second;
                finalLogoNumber = 'darker';
            }
        }

        if (finalItem !== undefined && finalItem !== false) {

            // customLog(`finalItem:${finalItem} id:${product_id} user:${user_id} finalLogoNumber:${finalLogoNumber}`, resultItem);

            let imgData = {
                url: finalLogo,
                product_id: product_id,
                user_id: user_id,
                custom_logo: custom_logo,
                finalLogoNumber: finalLogoNumber,
                logoNumber: logoNumber,
                is_feature: is_feature_image
            };
            
            // Loop through the logo data and draw each logo on the canvas
            for (const [index, logoInfo] of finalItem.entries()) {
                let { x, y, width, height, angle, custom } = logoInfo;

                imgData['custom'] = custom;

                const logoImage = await loadLogoImage(imgData);

                // customLog(`--- is_feature:${is_feature_image} custom:${custom} id:${product_id} user:${user_id}`);

                // if custom then check logo_type by image size
                // then get that type value from resultItem
                // and re-initialize x, y, width, height, angle again with new values.
                if( custom === true ) {
                    customLog(`custom ${custom} custom_logo ${custom_logo}`);
                    let get_type = get_orientation(logoImage);
                    if (custom_logo_type && (custom_logo_type === "horizontal" || custom_logo_type === "square")) {
                        // customLog(`ProductID:${product_id} Type:${custom_logo_type}`);
                        get_type = custom_logo_type;
                    }

                    // overwrite get_type if custom_logo[finalLogoNumber] == false. in short if custom logo with finalLogoNumber is emmpty.
                    if (
                        custom_logo !== undefined &&
                        custom_logo.hasOwnProperty(finalLogoNumber) && 
                        custom_logo[finalLogoNumber] == false
                    ) {
                        get_type = logo_type;
                    }
                    

                    let get_type_values = resultItem.meta_value[get_type];
                    
                    customLog("get_type", get_type, get_type_values);
                    if( get_type_values[index] && get_type_values[index] != null && get_type_values[index] != undefined ) {

                        // customLog(`--- get_type:${get_type} is_feature:${is_feature_image} id:${product_id} user:${user_id} index:${index}`, get_type_values);

                        ({ x, y, width, height, angle } = get_type_values[index]);
                    }
                }

                // Use the original width and height of the logo
                const originalWidth = logoImage.width;
                const originalHeight = logoImage.height;

                customLog('enableBackgroundColor', enableBackgroundColor, typeof enableBackgroundColor);

                if ( enableBackgroundColor === 'true' ) {
                    // Draw the background with rotation
                    ctx.save();
                    ctx.translate(x + width / 2, y + height / 2);
                    ctx.rotate(angle);
                    ctx.fillStyle = "lightblue"; // Set background color, you can change this to any color
                    ctx.fillRect(-width / 2, -height / 2, width, height);
                    ctx.restore();

                    customLog('added a logo background!');
                }

                // Calculate the new dimensions while maintaining the aspect ratio
                const { newWidth, newHeight } = calculateNewDimensions(originalWidth, originalHeight, width, height);

                // Calculate new y position to keep the image centered
                const newY = calculateCenteredY(y, height, newHeight);

                ctx.save();
                ctx.translate(x + width / 2, newY + newHeight / 2);
                ctx.rotate(angle);
                ctx.drawImage(logoImage, -newWidth / 2, -newHeight / 2, newWidth, newHeight);
                ctx.restore();
            }

            // Get the image data from the OffscreenCanvas
            const imageData = ctx.getImageData(0, 0, staticCanvas.width, staticCanvas.height);

            // Convert ImageData to data URL
            const dataURL = await canvasToDataUrl(imageData);

            // Call the function and wait for the result
            // const result = await saveImageToServer(dataURL, filename, user_id, is_feature_image);

            // Add image data to the batch array
            imageBatch.push({
                dataURL,
                filename,
                user_id,
                is_feature_image,
            });

            totalImagesProcessed++;

            customLog("totalNumberItems", totalNumberItems);
            customLog("getTotalItemNeedProcess", getTotalItemNeedProcess);

            // If the batch size reaches itemPushEachAtOnce or it's the last iteration, send the batch to the server
            if (
                imageBatch.length === itemPushEachAtOnce || 
                (totalImagesProcessed > 0 && totalImagesProcessed % itemPushEachAtOnce === 0) || 
                totalNumberItems === getTotalItemNeedProcess
            ) {
                customLog("totalImagesProcessed", totalImagesProcessed);
                const result = await saveImageBatchToServer(imageBatch);

                // Clear the batch array after sending it to the server
                imageBatch = [];

                // Handle the result if needed
                if (!result) {
                    console.error('Image batch save operation failed');
                    return false;
                }
            }

            return filename;
        }
    }
};


// Function to calculate new dimensions while maintaining aspect ratio
function calculateNewDimensions(originalWidth, originalHeight, maxWidth, maxHeight) {
    const aspectRatio = originalWidth / originalHeight;
  
    // Check if resizing is needed
    if (originalWidth > maxWidth || originalHeight > maxHeight) {
      if (maxWidth / aspectRatio <= maxHeight) {
        return { newWidth: maxWidth, newHeight: maxWidth / aspectRatio };
      } else {
        return { newWidth: maxHeight * aspectRatio, newHeight: maxHeight };
      }
    } else {
      return { newWidth: originalWidth, newHeight: originalHeight };
    }
}

  // Function to calculate new y position to keep the image centered
function calculateCenteredY(originalY, originalHeight, newHeight) {
    return originalY + (originalHeight - newHeight) / 2;
}

function get_orientation(attachment_metadata) {
    // Get attachment metadata
    if (attachment_metadata) {

        // Calculate the threshold for height to be less than 60% of width
        const heightThreshold = 0.6 * attachment_metadata.width;

        // Check if width and height are equal (square)
        if (attachment_metadata.width === attachment_metadata.height) {
            return 'square';
        } else if (attachment_metadata.height < heightThreshold) {
            return 'horizontal';
        } else {
            return 'square';
        }
    }
    return 'square';
}


// Function to load a logo image
const loadLogoImage = async (imgData) => {
    const { url, product_id, user_id, is_feature, custom, custom_logo, finalLogoNumber, logoNumber } = imgData;

    let fetchUrl = url;
    if( undefined != custom && true === custom && custom_logo != null) {
        if (
            custom_logo.hasOwnProperty("allow_products") && 
            Array.isArray(custom_logo.allow_products) && 
            custom_logo.allow_products.includes(product_id)
        ) {
            if (
                custom_logo.hasOwnProperty(finalLogoNumber) && 
                custom_logo[finalLogoNumber] && 
                custom_logo.finalLogoNumber !== ""
            ) {
                fetchUrl = custom_logo[finalLogoNumber];
            }
        }
    }
    const logoResponse = await fetch(fetchUrl);
    // customLog(`is_feature:${is_feature} custom:${custom} url:${fetchUrl} id:${product_id} user:${user_id}`, logoResponse);
    if (!logoResponse.ok) {
        throw new Error(`Failed to fetch logo image: ${logoResponse.status} ${logoResponse.statusText} is_feature:${is_feature} url:${url} id:${product_id} user:${user_id}`);
    }
    const logoBlob = await logoResponse.blob();
    return await createImageBitmap(logoBlob);
};

const loadSimpleImage = async (url) => {
    const logoResponse = await fetch(url);
    if (!logoResponse.ok) {
        throw new Error(`Failed to fetch logo image: ${url}`);
    }
    const logoBlob = await logoResponse.blob();
    return await createImageBitmap(logoBlob);
};



let imageBatch = [];
let totalImagesProcessed = 0;
let totalNumberItems = 0;
let getTotalItemNeedProcess = 0;

// Function to perform the image generation
const generateImages = async (task) => {
    let { backgrounds, logo, logo_second, custom_logo, user_id, logoData, logo_type, custom_logo_type, logo_collections } = task;
    const totalImages = backgrounds.length;
    const imageResultList = [];

    

    customLog('logo_collections:', logo_collections);

    for (let i = 0; i < totalImages; i++) {
        totalNumberItems++;
        const galleries = backgrounds[i]['galleries'];

        // If there are galleries, generate images for each gallery
        if (galleries && galleries.length !== 0) {
            totalNumberItems += galleries.length;
        }
    }

    customLog('finished totalNumberItems:', totalNumberItems);

    for (let i = 0; i < totalImages; i++) {
        getTotalItemNeedProcess++;
        const backgroundUrl = backgrounds[i]['url'];
        const product_id = backgrounds[i]['id'];
        const galleries = backgrounds[i]['galleries'];

        let storeLogo = logo;
        let storeLogoType = logo_type;
        let storeLogoSecond = logo_second;

        // first check if logo collection even exists or not
        if( logo_collections !== null && logo_collections.length !== 0 && logo_collections.collections !== null ) {
            const itemData = await getLightnessByID(logo_collections.collections, product_id);

            const { override_logo } = logo_collections;

            // check if itemData is not null
            // although it's check first so it's just extra layour of security
            if (itemData !== null) {

                customLog("itemData::::::::::", itemData);
                customLog("product_id::::::::::", product_id);
                customLog("logo_collections::::::::::", logo_collections.collections);

                storeLogo = await getLighter( itemData, logo );
                storeLogoSecond = await getDarker( itemData, logo_second );

                if( logo && logo !== null && ( override_logo === '' || override_logo === false) ) {
                    
                    // check if itemData.shape is empty or value is square or horizontal
                    if( itemData.shape !== '' && ( itemData.shape === 'square' || itemData.shape === 'horizontal' ) ) {
                        storeLogoType = itemData.shape;
                    }

                    customLog(`override_logo: ${override_logo} newtype: ${storeLogoType}`);
                }

                customLog("storeLogoType::::::::::", storeLogoType);

                customLog(`user_id ${user_id} logo ${logo} logo_second ${logo_second} logo_type ${logo_type}`);
            }
        }
        

        // Generate image for the main product
        const mainImageResult = await generateImageWithLogos(backgroundUrl, user_id, product_id, storeLogo, storeLogoSecond, custom_logo, logoData, storeLogoType, custom_logo_type);
        imageResultList.push(mainImageResult);

        // If there are galleries, generate images for each gallery
        if (galleries && galleries.length !== 0) {
            const galleriesConvert = convertGallery(galleries);
            
            for (const item of galleriesConvert) {
                const galleryUrl = item['url'];
                const galleryItem = item;
                getTotalItemNeedProcess++;
                
                // Generate image for the gallery
                const galleryImageResult = await generateImageWithLogos(galleryUrl, user_id, product_id, storeLogo, storeLogoSecond, custom_logo, logoData, storeLogoType, custom_logo_type, galleryItem);
                imageResultList.push(galleryImageResult);
            }
        }
    }

    // Filter out the false values (failed image generation)
    const filteredResultList = imageResultList.filter(result => result !== false);

    return filteredResultList; // Return the result list if needed elsewhere
};

/**
 * Retrieves the lighter and darker logo by product ID
 *
 * @param {Array} data - the array of objects containing lightness information
 * @param {string} productId - the ID of the product to retrieve lightness information for
 * @return {Array|null} an array of objects containing logo_lighter and logo_darker properties, or null if no matching lightness information is found
 */
async function getLightnessByID(data, productId) {
    
    for (let key = 0; key < data.length; key++) {
      const item = data[key];
      
      // Check if at least one of logo_lighter or logo_darker is not empty
      if ((item.logo_lighter !== '' || item.logo_darker !== '') && item.select_products.includes(productId)) {
        return {
          lighter: item.logo_lighter,
          darker: item.logo_darker,
          shape: item?.shape && item.shape !== '-- Select --' ? item.shape.toLowerCase() : ''
        }
      }
    }
    
    return null;
}

async function getLighter( data, logo ) {
    if ( data && data.lighter && data.lighter !== false ) {
        return data.lighter;
    }

    return logo;
}

async function getDarker( data, logo ) {
    if ( data && data.darker && data.darker !== false ) {
        return data.darker;
    }

    return logo;
}

// Function to convert ImageData to data URL
async function canvasToDataUrl(imageData) {
    const tempCanvas = document.createElement('canvas');
    const tempContext = tempCanvas.getContext('2d');

    // Set the canvas size to match the ImageData
    tempCanvas.width = imageData.width;
    tempCanvas.height = imageData.height;

    // Put the ImageData onto the canvas
    tempContext.putImageData(imageData, 0, 0);

    // Convert the canvas content to data URL
    const dataUrl = tempCanvas.toDataURL('image/png');

    return dataUrl;
}

// Function to send the dataURL to the server
async function saveImageToServer(dataURL, filename, user_id, is_feature_image) {
    try {
        const response = await fetch(mockupGeneratorAjax.image_save_endpoint, {
            method: 'POST',
            body: JSON.stringify({ imageData: dataURL, filename, user_id, is_feature_image }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Image was successfully saved on the server
            customLog('Image saved on the server');
            return true; // or you can return some other value indicating success
        } else {
            // Handle the error if the save operation fails
            console.error('Failed to save image on the server');
            return false; // or you can return some other value indicating failure
        }
    } catch (error) {
        console.error('Error sending data to the server:', error);
        return false; // or you can return some other value indicating failure
    }
}

async function saveInfo(user_id, start_time, end_time, total_items) {
    customLog("saveInfo", user_id, start_time, end_time, total_items);
    try {
        const response = await fetch(mockupGeneratorAjax.info_save_endpoint, {
            method: 'POST',
            body: JSON.stringify({ user_id, start_time, end_time, total_items }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Image was successfully saved on the server
            customLog('Image saved on the server');
            return true; // or you can return some other value indicating success
        } else {
            // Handle the error if the save operation fails
            console.error('Failed to save image on the server');
            return false; // or you can return some other value indicating failure
        }
    } catch (error) {
        console.error('Error sending data to the server:', error);
        return false; // or you can return some other value indicating failure
    }
}

async function saveImageBatchToServer(batch) {
    try {
        customLog('batch', batch);
        const response = await fetch(mockupGeneratorAjax.image_save_endpoint, {
            method: 'POST',
            body: JSON.stringify({ batch }),
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (response.ok) {
            // Image batch was successfully saved on the server
            customLog('Image batch saved on the server');
            return true; // or you can return some other value indicating success
        } else {
            // Handle the error if the save operation fails
            console.error('Failed to save image batch on the server');
            return false; // or you can return some other value indicating failure
        }
    } catch (error) {
        console.error('Error sending data to the server:', error);
        return false; // or you can return some other value indicating failure
    }
}

const processUserQueue = async () => {
    while (userQueue.length > 0) {
        customLog( "userQueue start: " + new Date() );
        const user = userQueue.shift(); // Dequeue the first user from the queue
        customLog(user);
        const { backgrounds, logo, logo_second, user_id, logoData, logo_type, custom_logo_type, logo_collections } = user;

        const start_time = Math.floor(Date.now() / 1000);

        try {
            imageBatch = [];
            totalImagesProcessed = 0;
            totalNumberItems = 0;
            getTotalItemNeedProcess = 0;
            isGeneratingImages = true; // Set the flag to indicate image generation is in progress
            const result = await generateImages({ backgrounds, logo, logo_second, user_id, logoData, logo_type, custom_logo_type, logo_collections });

            // Do something with the result if needed
            if(result) {
                let btnItem = $('#ml_mockup_gen-'+user_id);
                let checkboxItem = $('input.customer[value="'+user_id+'"]');
                if( btnItem.length !== 0 ) {
                    btnItem.removeClass('ml_loading').prop("disabled", false);
                }
                if( checkboxItem.length !== 0 ) {
                    checkboxItem.prop("checked", false);
                }
            }
            
            const info = {
                "user_id": user_id,
                "start_time": start_time,
                "end_time": Math.floor(Date.now() / 1000),
                "total_items": result.length
            }
            
            // Call the function to print the result after all images are generated
            printImageResultList(info);
        } catch (error) {
            console.error('Error generating images for user:', user, error);
        } finally {
            isGeneratingImages = false; // Reset the flag once image generation is complete
        }
    }

    // Print a message if the queue is empty after processing
    if (userQueue.length === 0) {
        customLog('All users in the queue have been processed.');
        alert("Generation Done!");
        // refresh the page
        location.reload();
    }
};

// Helper function to get selected user IDs from checkboxes
const getSelectedUserIds = () => {
    const checkboxElements = document.querySelectorAll('input[name="users[]"]:checked');
    const selectedUserIds = [];

    checkboxElements.forEach((checkbox) => {
        // Check if the parent contains an element with class "ml_mockup_gen_trigger"
        const parent = checkbox.closest('tr');
        if (parent && parent.querySelector('.ml_mockup_gen_trigger')) {
            // Include the user ID in the array of selected user IDs
            selectedUserIds.push(checkbox.value);
        } else {
            checkbox.checked = false;
        }
    });

    return selectedUserIds;
};


// Assuming you have a function to get user data based on user ID
const getUserDataById = (userId) => {

    let btnItem = $('#ml_mockup_gen-'+userId);
    if( btnItem.length !== 0 ) {
        const task = getItemData(btnItem);
        return task;
    }

    return false;
};


// Assuming you have a function to handle the bulk action apply button click
const handleBulkActionApply = async (event) => {

    // Check if the bulk action selector value is "alaround_mockup_gen"
    const bulkActionSelector = document.getElementById("bulk-action-selector-top");
    const bulkActionSelectorValue = bulkActionSelector.value;

    if (bulkActionSelectorValue !== "alaround_mockup_gen") {
        customLog('Bulk action does not match "alaround_mockup_gen". Ignoring.');
        return;
    }

    const doActionButton = document.getElementById("doaction");

    event.preventDefault();

    if (isGeneratingImages) {
        customLog('Image generation is already in progress. Please wait.');
        return;
    }

    const selectedUserIds = getSelectedUserIds();

    if (selectedUserIds.length === 0) {
        customLog('No users selected.');
        return;
    }

        // Set the flag to indicate image generation is in progress
        isGeneratingImages = true;

    // Disable the bulk action selector and do action button
    bulkActionSelector.disabled = true;
    doActionButton.disabled = true;

    // Filter out users without .ml_mockup_gen_trigger
    const selectedUsersWithLogo = selectedUserIds
        .map(userId => getUserDataById(userId))
        .filter(userData => userData !== false);

    // Enqueue the selected users
    userQueue.push(...selectedUsersWithLogo);

    try {
        await processUserQueue();
    } catch (error) {
        console.error('Error during bulk image generation:', error);
    } finally {
            // Reset the flag once image generation is complete
            isGeneratingImages = false;

        // Re-enable the bulk action selector and do action button
        bulkActionSelector.disabled = false;
        doActionButton.disabled = false;
    }
};

// Show a warning message when the user tries to leave or close the tab
window.addEventListener('beforeunload', (event) => {
    if (isGeneratingImages) {
        const message = 'Leaving this page while the bulk action is in progress may result in data loss.';
        (event || window.event).returnValue = message; // Standard method
        return message; // For some older browsers
    }
});

// event listener for the bulk action apply button
const doActionButton = document.getElementById('doaction');
if (doActionButton) {
    doActionButton.addEventListener('click', handleBulkActionApply);
}

function isValidUrl(url) {
    try {
        new URL(url);
        return true;
    } catch (error) {
        return false;
    }
}

function getItemData(elm) {
    if (elm.length === 0)
        return false;

    let settings = elm.data('settings');

    if (settings.length === 0)
        return false;

    if (
        !settings.images ||
        settings.images.length === 0 ||
        !settings.logo ||
        !settings.user_id
    ) {
        customLog("required variables are undefined");
        return false;
    }

    const backgrounds = convertBackgrounds(settings.images);
    if(!backgrounds) {
        return false;
    }


    let logoData = '';
    if (settings.logo_positions && settings.logo_positions.length !== 0) {
        logoData = convertLogos(settings.logo_positions);
    }

    let logo_type = settings.logo_type;

    const logo = settings.logo;
    const user_id = settings.user_id;
    let logo_second = settings.logo_second;
    let custom_logo = settings.custom_logo_data;
    let logo_collections = settings.logo_collections;
    let custom_logo_type = settings.custom_logo_type;
    // let custom_logo = undefined;

    if (logo_second && !isValidUrl(logo_second)) {
        // customLog('logo_second is not a valid URL. Setting to undefined or default.');
        logo_second = undefined; // or set to a default value
    }

    elm.addClass('ml_loading');

    const task = { backgrounds, logo, logo_second, custom_logo, user_id, logoData, logo_type, custom_logo_type, logo_collections };

    // customLog(task);

    return task;
}

$(document).on('click', ".ml_mockup_gen_trigger", async function () {
    var item = $(this);

    // Check if image generation is already in progress
    if (isGeneratingImages) {
        customLog('Image generation is already in progress. Please wait.');
        return;
    }

    const task = getItemData(item);

    const { user_id } = task;

    const start_time = Math.floor(Date.now() / 1000);

    try {
        // Set the flag to indicate image generation is in progress
        isGeneratingImages = true;

        imageBatch = [];
        totalImagesProcessed = 0;
        totalNumberItems = 0;
        getTotalItemNeedProcess = 0;

        // Add the "ml_loading" class to the clicked item
        item.addClass('ml_loading');

        customLog( "start: " + new Date() );

        // Perform image generation
        imageResultList = await generateImages(task);
    } catch (error) {
        console.error('Error:', error);
    } finally {
        // Reset the flag once image generation is complete
        isGeneratingImages = false;

        // Remove the "ml_loading" class from the clicked item
        item.removeClass('ml_loading');

        const info = {
            "user_id": user_id,
            "start_time": start_time,
            "end_time": Math.floor(Date.now() / 1000),
            "total_items": imageResultList.length
        }

        // Call the function to print the result after all images are generated
        printImageResultList(info);
    }

    return false;
});


// Print the result after all images are generated
function printImageResultList(info) {
    const { user_id, start_time, end_time, total_items } = info;
    saveInfo( user_id, start_time, end_time, total_items );
    customLog('imageResultList', imageResultList);
    customLog( "finished: " + new Date() );
}


// T̶O̶D̶O̶:̶ U̶n̶c̶h̶e̶c̶k̶ i̶f̶ u̶s̶e̶r̶ d̶o̶n̶t̶ h̶a̶v̶e̶ l̶o̶g̶o̶.̶ .̶m̶l̶_̶m̶o̶c̶k̶u̶p̶_̶g̶e̶n̶_̶t̶r̶i̶g̶g̶e̶r̶ c̶a̶n̶ b̶e̶ u̶s̶e̶ f̶o̶r̶ t̶h̶i̶s̶ c̶a̶s̶e̶.̶
// TODO: When one user running make button disable
// T̶O̶D̶O̶:̶ I̶f̶ b̶u̶l̶k̶ r̶u̶n̶n̶i̶n̶g̶ t̶h̶e̶n̶ s̶e̶l̶e̶c̶t̶ a̶n̶d̶ d̶o̶a̶c̶t̶i̶o̶n̶ m̶a̶k̶e̶ d̶i̶s̶a̶b̶l̶e̶
// T̶O̶D̶O̶:̶ U̶n̶c̶h̶e̶c̶k̶ w̶h̶e̶n̶ o̶n̶e̶ u̶s̶e̶r̶ d̶o̶n̶e̶ a̶n̶d̶ a̶l̶s̶o̶ r̶e̶m̶o̶v̶e̶ l̶o̶a̶d̶i̶n̶g̶ f̶r̶o̶m̶ b̶u̶t̶t̶o̶n̶
// T̶O̶D̶O̶:̶ S̶h̶o̶w̶ a̶l̶e̶r̶t̶ w̶h̶e̶n̶ b̶u̶l̶k̶ a̶l̶l̶ q̶u̶e̶u̶e̶ d̶o̶n̶e̶.̶




})(jQuery);