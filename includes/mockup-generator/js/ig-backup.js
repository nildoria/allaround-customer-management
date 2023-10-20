self.addEventListener('message', function (e) {
    if (e.data.type === 'generateImages') {
        const { backgrounds, logo, user_id, logoData } = e.data;

        console.log("logoData");
        console.log(logoData);

        // Function to generate an image with logo
        const generateImageWithLogo = async (backgroundUrl, product_id, logoUrl) => {
            try {
                // Extract the filename from the background URL
                // const backgroundFilename = backgroundUrl.substring(backgroundUrl.lastIndexOf('/') + 1);

                const file_ext = getFileExtensionFromUrl(backgroundUrl);
                const filename = product_id + '.' + file_ext;

                // Load background image as Blob
                const backgroundResponse = await fetch(backgroundUrl);
                const backgroundBlob = await backgroundResponse.blob();
                const backgroundImage = await createImageBitmap(backgroundBlob);

                // Load logo image as Blob
                const logoResponse = await fetch(logoUrl);
                const logoBlob = await logoResponse.blob();
                const logoImage = await createImageBitmap(logoBlob);

                // Create a canvas element to work with
                const staticCanvas = new OffscreenCanvas(backgroundImage.width, backgroundImage.height);
                const ctx = staticCanvas.getContext('2d');

                // Draw the background image
                ctx.drawImage(backgroundImage, 0, 0);

                // Define logo properties
                const logo = {
                    x: 100,
                    y: 100,
                    width: 100,
                    height: 100,
                    angle: 0,
                };

                // Draw the logo on the canvas
                ctx.save();
                ctx.translate(logo.x + logo.width / 2, logo.y + logo.height / 2);
                ctx.rotate(logo.angle);
                ctx.drawImage(logoImage, -logo.width / 2, -logo.height / 2, logo.width, logo.height);
                ctx.restore();

                // Get the image data from the OffscreenCanvas
                const imageData = ctx.getImageData(0, 0, staticCanvas.width, staticCanvas.height);

                // Send the image data back to the main page
                self.postMessage({ type: 'imageGenerated', imageData, filename, user_id });
            } catch (error) {
                console.error('Error generating image:', error);
            }
        };

        // Loop through all backgrounds and generate images with logos
        for (let i = 0; i < backgrounds.length; i++) {
            const backgroundUrl = backgrounds[i]['url'];
            const product_id = backgrounds[i]['id'];
            console.log(backgroundUrl);
            generateImageWithLogo(backgroundUrl, product_id, logo);
        }
    }
});


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