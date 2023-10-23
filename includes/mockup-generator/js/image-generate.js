self.addEventListener('message', function (e) {
    if (e.data.type === 'generateImages') {
        const { backgrounds, logo, logo_second, user_id, logoData, logo_type } = e.data;

        console.log( 'logoData', logoData );
        console.log( 'logo_type', logo_type );
        console.log( 'logo_second', logo_second );

        // Function to send progress updates to the main thread
        const sendProgress = (progress) => {
            self.postMessage({ type: 'progress', progress, user_id });
        };

        // Define an async function to perform the image generation
        const generateImages = async () => {

            let totalBackgrounds = backgrounds.length;

            for (let i = 0; i < backgrounds.length; i++) {
                let galleries = backgrounds[i]['galleries'];
                if( galleries.length !== 0 ) {
                    totalBackgrounds += galleries.length;
                }
            }

            console.log( "total bgcs", totalBackgrounds );
            let completedBackgrounds = 0;



            // Function to generate an image with logos
            const generateImageWithLogos = async (backgroundUrl, product_id, logo, logoData, logo_type, gallery = false) => {
                try {
                    // Extract the filename from the background URL
                    const file_ext = getFileExtensionFromUrl(backgroundUrl);
                    let filename = product_id + '.' + file_ext;

                    console.log("gallery", gallery);
                    if( gallery && gallery !== false && gallery.length !== 0 ) {
                        filename = product_id + '-' + gallery['id'] + '-' + gallery['attachment_id'] + '.' + file_ext;
                    }
                    console.log("filename", filename);

                    // Load background image as Blob
                    const backgroundResponse = await fetch(backgroundUrl);
                    // console.log( backgroundUrl, product_id );
                    // console.log( backgroundResponse );
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

                    // console.log( 'itemsWithMatchingProductID', itemsWithMatchingProductID );

                    // Find an item with the matching meta_key "ml_logos_positions_{user_id}"
                    const matchingItem = itemsWithMatchingProductID.find(item => item.meta_key === `ml_logos_positions_${user_id}`);

                    // console.log( 'matchingItem', matchingItem );

                    // If found, use it; otherwise, fall back to "ml_logos_positions"
                    const resultItem = matchingItem || itemsWithMatchingProductID.find(item => item.meta_key === "ml_logos_positions");

                    // console.log( 'resultItem', resultItem );

                    if (resultItem != undefined) {
                        let finalItem = resultItem.meta_value[logo_type];
                        let logoNumber = resultItem.meta_value['logoNumber'];
                            logoNumber = logoNumber !== undefined ? logoNumber : 'default';

                        // check if select second logo or not
                        // check if second logo value exists or not
                        let finalLogo = logoNumber === 'second' && (logo_second && logo_second !== null && logo_second !== undefined) ? logo_second : logo;

                        if( gallery && gallery !== false && gallery.length !== 0 ) {
                            
                            if( gallery['type'] == 'light' ) {
                                finalLogo = logo;
                            }
                            if( gallery['type'] == 'dark' && (logo_second && logo_second !== null && logo_second !== undefined) ) {
                                finalLogo = logo_second;
                            }
                        }

                        console.log("finalLogo", finalLogo);

                        console.log( 'finalItem', finalItem );

                        if (finalItem !== undefined && finalItem !== false) {
                            // Loop through the logo data and draw each logo on the canvas
                            for (const logoInfo of finalItem) {
                                const logoImage = await loadLogoImage(finalLogo);

                                // Use the original width and height of the logo
                                const originalWidth = logoImage.width;
                                const originalHeight = logoImage.height;

                                const { x, y, width, height, angle } = logoInfo;

                                const newHeight = aspect_height(originalWidth, originalHeight, width);
                                const newY =  aspectY(newHeight, height, y);

                                ctx.save();
                                ctx.translate(x + width / 2, newY + newHeight / 2);
                                ctx.rotate(angle);
                                ctx.drawImage(logoImage, -width / 2, -newHeight / 2, width, newHeight);
                                ctx.restore();
                            }

                            // Get the image data from the OffscreenCanvas
                            const imageData = ctx.getImageData(0, 0, staticCanvas.width, staticCanvas.height);

                            // console.log( filename );
                            // console.log( "user_id " + user_id );

                            // Send the image data back to the main page
                            self.postMessage({ type: 'imageGenerated', imageData, filename, user_id });

                            // Increment the completed backgrounds count
                            completedBackgrounds++;

                            // Calculate and send progress
                            const progress = Math.floor((completedBackgrounds / totalBackgrounds) * 100);
                            sendProgress(progress);
                            console.log( "completedBackgrounds", completedBackgrounds );
                            console.log( "totalBackgrounds", totalBackgrounds );

                            if (progress % 10 === 0) {
                                console.log(`Progress: ${progress}%`);
                            }
                        }
                    }
                } catch (error) {
                    console.error('Error generating image:', error);
                }
            };

            // Function to load a logo image
            const loadLogoImage = async (logoUrl) => {
                try {
                    const logoResponse = await fetch(logoUrl);
                    if (!logoResponse.ok) {
                        throw new Error(`Failed to fetch logo image: ${logoResponse.status} ${logoResponse.statusText}`);
                    }
                    const logoBlob = await logoResponse.blob();
                    return await createImageBitmap(logoBlob);
                } catch (error) {
                    console.error('Error loading logo image:', error);
                    throw error; // Re-throw the error to be caught in the generateImageWithLogos function
                }
            };

            // Loop through all backgrounds and generate images with logos
            console.log( "backgroundLenght", backgrounds.length );
            for (let i = 0; i < backgrounds.length; i++) {
                const backgroundUrl = backgrounds[i]['url'];
                const product_id = backgrounds[i]['id'];
                let galleries = backgrounds[i]['galleries'];
                console.log("backgroundItem", backgrounds[i]);
                try {
                    await generateImageWithLogos(backgroundUrl, product_id, logo, logoData, logo_type);
                } catch (error) {
                    // Handle the error (e.g., log it) and continue with the next item
                    console.error(`Error generating image for product_id ${product_id}:`, error);
                    continue;
                }
                if( galleries && galleries !== null && galleries.length !== 0 ) {
                    const galleriesConvert = convertGallery(galleries);
                    console.log("galleriesConvert", galleriesConvert);

                    galleriesConvert.forEach(async (item, index) => {
                        const galleryUrl = item['url'];
                        let galleryItem = item;
                        
                        try {
                            // Call generateImageWithLogos for each gallery item and await the result
                            await generateImageWithLogos(galleryUrl, product_id, logo, logoData, logo_type, galleryItem);
                        } catch (error) {
                            // Handle the error (e.g., log it)
                            console.error(`Error generating image for gallery item at index ${index}:`, error);
                        }
                    });
                }
            }
        };

        // Call the async function to generate images
        generateImages();
    }
});


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