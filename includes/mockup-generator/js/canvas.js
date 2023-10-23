document.addEventListener('DOMContentLoaded', function () {

    const logoTypesSelect = document.getElementById('logoTypes');
    const mergedCanvas = document.getElementById('mergedCanvas');
    const logoNumberBtn = document.getElementById('logoNumber');
    const addLogoBtn = document.getElementById('addLogo');
    const rotationInput = document.getElementById('rotationInput');
    const rotateLeftBtn = document.getElementById('rotateLeft');
    const rotateRightBtn = document.getElementById('rotateRight');
    const deselectLogoBtn = document.getElementById('deselectLogo');
    const removeLogoBtn = document.getElementById('removeLogo');
    const logoSelector = document.getElementById('logoSelector');
    const undoResizeBtn = document.getElementById('undoResizeBtn');
    const removeUserDataBtn = document.getElementById('removeUserDataBtn');
    const productId = document.getElementById('ml_product_id');
    const mainWrap = document.getElementById('alarnd--main-canvas-editor-wrap');
    const alarndSaveCanvasBtn = document.getElementById('alarndSaveCanvas');
    const showInfoBtn = document.getElementById('showInfo');
    const container = document.querySelector('.alarnd--canvas-container');
    const ctx = mergedCanvas.getContext('2d');
    let logos = [];
    let selectedLogo = null;
    let isDragging = false;
    let isResizing = false;
    // let isDefault = false;
    let resizeHandle = '';
    let logoType = 'square';
    let settings = mergedCanvas.getAttribute('data-settings');
        settings = JSON.parse(settings);

        console.log( settings );

    let saved_positions = settings.positions ? settings.positions : undefined;
    console.log( saved_positions );
        
    let logo_src = settings.logo[logoType];
    let original_logo_src = logo_src;
    // Define a variable to store the original dimensions of the logo before resizing
    let originalLogoDimensions = null;


    // Background Image
    const backgroundImage = new Image();
    backgroundImage.src = settings.background;

    backgroundImage.onload = () => {
        // Calculate the canvas size based on the background image

        const maxWidth = 1000; // Maximum width
        const scale = Math.min(maxWidth / backgroundImage.width, 1);
        mergedCanvas.width = backgroundImage.width;
        mergedCanvas.height = backgroundImage.height;

        // Preload logo images
        const logoImage1 = new Image();
        logoImage1.src = logo_src; // Replace with the actual path to your logo images

        // Wait for both logo images to load
        Promise.all([logoImage1].map(image => new Promise((resolve, reject) => {
            image.onload = resolve;
            image.onerror = reject;
        })))
            .then(() => {
      
                // Use the original width and height of the logo
                const originalWidth = logoImage1.width;
                const originalHeight = logoImage1.height;

                const selectedLogoType = logoTypesSelect.value;
                console.log("selectedLogoType", selectedLogoType);

                let is_saved_found = false;
                const finalItem = get_position_values( false, selectedLogoType );
                if (finalItem !== undefined && finalItem !== false) {

                    const logoNumber = getDefaultLogoNumber();
                    if( logoNumber && logoNumber !== false ) {
                        logoNumberBtn.value = logoNumber;
                    }
                    
                    // Loop through the logo data and draw each logo on the canvas
                    for (const logoInfo of finalItem) {
                        const { x, y, width, height, angle } = logoInfo;

                        const newHeight = aspect_height(originalWidth, originalHeight, width);
                        const newY =  aspectY(newHeight, height, y);

                        const newLogo = {
                            x: x,
                            y: newY,
                            width: width,
                            height: newHeight,
                            angle: angle,
                            image: logoImage1
                        };

                        logos.push(newLogo);
                    }
                    is_saved_found = true;
                }

                if (is_saved_found !== true) {
                    // Add the preloaded logos to the logos array
                    logos.push({
                        x: 100,
                        y: 100,
                        width: originalWidth,
                        height: originalHeight,
                        angle: 0,
                        image: logoImage1,
                    });
                }

                // Draw the canvas with the background and logos
                draw();

                // Get the original width of the element
                const theoriginalWidth = mergedCanvas.offsetWidth;
                const theoriginalHeight = mergedCanvas.offsetHeight;

                console.log( "theoriginalWidth", theoriginalWidth );

                // Calculate the scale factor to fit within a max width of 1000px
                const scaleFactor = Math.min(1, 1000 / theoriginalWidth);

                // Apply the scale transform
                mergedCanvas.style.transform = `scale(${scaleFactor})`;

                // Calculate the scaled height
                const scaledHeight = theoriginalHeight * scaleFactor;
                console.log(scaledHeight);
                container.style.height = scaledHeight+'px';
            })
            .catch(error => {
                console.error('Error preloading logos:', error);
            });
    };

    function draw() {
        ctx.clearRect(0, 0, mergedCanvas.width, mergedCanvas.height);
        ctx.drawImage(backgroundImage, 0, 0, mergedCanvas.width, mergedCanvas.height);

        // Set the global alpha to 1 for full opacity
        ctx.globalAlpha = 1;

        logos.forEach((logo) => {
            ctx.save();
            ctx.translate(logo.x + logo.width / 2, logo.y + logo.height / 2);
            ctx.rotate(logo.angle);
            ctx.drawImage(logo.image, -logo.width / 2, -logo.height / 2, logo.width, logo.height);
            ctx.restore();

            if (selectedLogo === logo) {
                drawResizeHandles(logo);
            }
        });

        // Reset the global alpha to its default value (1)
        ctx.globalAlpha = 1;
    }

    function drawResizeHandles(logo) {
        const handles = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
        ctx.fillStyle = '#007bff';

        handles.forEach((handle) => {
            const x = logo.x + (handle.includes('right') ? logo.width : 0);
            const y = logo.y + (handle.includes('bottom') ? logo.height : 0);
            ctx.fillRect(x - 5, y - 5, 10, 10);
        });
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

    function get_position_values( userId = false, type = 'square' ) {
        if( saved_positions === undefined )
            return false;

        if( 
            userId && 
            saved_positions.users && 
            saved_positions.users[userId] !== undefined &&
            saved_positions.users[userId][type].length !== 0
        ) {
            removeUserDataBtn.disabled = false;
            return saved_positions.users[userId][type];
        }

        removeUserDataBtn.disabled = true;
        
        if( 
            saved_positions.ml_logos_positions && 
            saved_positions.ml_logos_positions[type].length !== 0
        ) {
            return saved_positions.ml_logos_positions[type];
        }

        return false;
    }
    
    function getLogoNumber( userId = false ) {
        if( saved_positions === undefined )
            return 'default';

            console.log("userId", userId);

        if( 
            userId && 
            saved_positions.users && 
            saved_positions.users[userId] !== undefined &&
            saved_positions.users[userId]['logoNumber'].length !== 0
        ) {
            return saved_positions.users[userId]['logoNumber'];
        }

        if( 
            userId === null && 
            saved_positions.ml_logos_positions && 
            saved_positions.ml_logos_positions['logoNumber'].length !== 0
        ) {
            return saved_positions.ml_logos_positions['logoNumber'];
        }

        return 'default';;
    }
    
    function getDefaultLogoNumber() {
        if( saved_positions === undefined )
            return 'default';

        if( 
            saved_positions.ml_logos_positions && 
            saved_positions.ml_logos_positions['logoNumber'].length !== 0
        ) {
            return saved_positions.ml_logos_positions['logoNumber'];
        }

        return 'default';;
    }

    function addLogoWithPositions(logoImage, userId = false) {
        const selectedLogoType = logoTypesSelect.value;

        // Use the original width and height of the logo
        const originalWidth = logoImage.width;
        const originalHeight = logoImage.height;

        // Set the initial coordinates for the new logo
        const centerX = mergedCanvas.width / 2 - originalWidth / 2;
        const centerY = mergedCanvas.height / 2 - originalHeight / 2;

        let is_saved_found = false;
        const finalItem = get_position_values( userId, selectedLogoType );
        
        if (finalItem !== undefined && finalItem !== false) {
            // Loop through the logo data and draw each logo on the canvas
            for (const logoInfo of finalItem) {
                const { x, y, width, height, angle } = logoInfo;

                const newHeight = aspect_height(originalWidth, originalHeight, width);
                const newY =  aspectY(newHeight, height, y);

                console.log( "logoInfo", logoInfo );
                console.log( "newHeight", newHeight );
                console.log( "newY", newY );

                const newLogo = {
                    x: x,
                    y: newY,
                    width: width,
                    height: newHeight,
                    angle: angle,
                    image: logoImage
                };

                logos.push(newLogo);
            }
            is_saved_found = true;
        }

        if (is_saved_found !== true) {
            console.log(logos);
            const newLogo = {
                x: centerX,
                y: centerY,
                width: originalWidth,
                height: originalHeight,
                angle: 0,
                image: logoImage,
            };

            logos.push(newLogo);
        }
    }

    logoSelector.addEventListener('change', (e) => {
        let selectedLogoPath = logoSelector.value;
        const selectedLogoType = logoTypesSelect.value;
        rotationInput.value = '0';

        const selectedOption = e.target.options[e.target.selectedIndex];
        const dataUserId = selectedOption.getAttribute('data-user_id');

        // logoNumberBtn.value = 'default';
        // logoNumberBtn.disabled = false;

        if( ! selectedLogoPath ) {
            selectedLogoPath = original_logo_src;
            // logoNumberBtn.disabled = true;
        }

        let logoNumber = getLogoNumber(dataUserId);
        console.log("logoNumber", logoNumber);
        if( logoNumber && logoNumber !== false ) {
            logoNumberBtn.value = logoNumber;
            const secondLogo = selectedOption.getAttribute('data-second_logo');
            if( logoNumber && 'second' === logoNumber && ( secondLogo && secondLogo !== undefined) ) {
                selectedLogoPath = secondLogo;
            }
        } else {
            logoNumberBtn.value = 'default';
        }

        if (selectedLogoPath) {
            const logoImage = new Image();
            logoImage.src = selectedLogoPath;
            logo_src = selectedLogoPath;
            console.log( logoImage );

            logoImage.onload = () => {
                clearCanvas();

                addLogoWithPositions( logoImage, dataUserId );

                // Immediately draw the new logo
                draw();
            };
        } else {
            clearCanvas();
        }
    });

    function change_trigger(elm, value = '') {
        elm.value = value;
        const changeEvent = new Event("change", { bubbles: true });
        elm.dispatchEvent(changeEvent);
    }

    // Add an event listener to the logoTypesSelect element
    logoTypesSelect.addEventListener('change', (e) => {
        const selectedLogoType = e.target.value;
    
        // Update the logo_src based on the selected logoType
        switch (selectedLogoType) {
            case 'square':
                original_logo_src = settings.logo['square'];
                break;
            case 'horizontal':
                original_logo_src = settings.logo['horizontal'];
                break;
            // Add more cases for other logo types as needed
            default:
                // Handle the default case or set a default logo source
                original_logo_src = settings.logo['square'];
                break;
        }
    
        const logoImage = new Image();
        logoImage.src = original_logo_src;
        logo_src = original_logo_src;

        change_trigger(logoSelector);
        rotationInput.value = '0';
        logos = [];
    
        logoImage.onload = () => {
            clearCanvas();
    
            addLogoWithPositions( logoImage, false );
    
            // Immediately draw the new logo
            draw();
        };
    });
    
    logoNumberBtn.addEventListener('change', (e) => {
        const logoNumber = e.target.value;
        const defaultLogo = logoSelector.value;

        if( ! defaultLogo || defaultLogo == undefined )
            return false;

        const selectedOption = logoSelector.options[logoSelector.selectedIndex];
        const userId = selectedOption.getAttribute('data-user_id');
        const secondLogo = selectedOption.getAttribute('data-second_logo');

        if( logoNumber && 'second' === logoNumber && ( secondLogo && secondLogo !== undefined) ) {
            original_logo_src = secondLogo;
        } else {
            original_logo_src = defaultLogo;
        }
    
        const logoImage = new Image();
        logoImage.src = original_logo_src;
        logo_src = original_logo_src;
    
        logos = [];
    
        logoImage.onload = () => {
            clearCanvas();
    
            addLogoWithPositions( logoImage, userId );
    
            // Immediately draw the new logo
            draw();
        };
    });
    

    undoResizeBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (selectedLogo && originalLogoDimensions) {
            selectedLogo.width = originalLogoDimensions.width;
            selectedLogo.height = originalLogoDimensions.height;
            originalLogoDimensions = null;
            draw();
        }
    });

    function addDisabled() {
        // Get all child buttons and input elements within the parent element
        const childButtonsAndInputs = mainWrap.querySelectorAll("button, input, select");

        // Disable all child buttons and input elements
        childButtonsAndInputs.forEach(function (element) {
            element.disabled = true;
        });
    }
    
    function removeDisabled() {
        // Get all child buttons and input elements within the parent element
        const childButtonsAndInputs = mainWrap.querySelectorAll("button, input, select");

        // Disable all child buttons and input elements
        childButtonsAndInputs.forEach(function (element) {
            element.disabled = false;
        });
    }
    
    removeUserDataBtn.addEventListener('click', (e) => {
        e.preventDefault();
        
        if (logos) {

            const selectedOption = logoSelector.options[logoSelector.selectedIndex];
            const user_id = selectedOption.getAttribute('data-user_id');
            const product_id = productId.value;
            logoType = logoTypesSelect.value;

            const xhr = new XMLHttpRequest();
            const url = canvasObj.ajax_url;

            // Create data object with the selected value
            const data = new FormData();
            data.append('action', 'remove_data');
            data.append('nonce', canvasObj.nonce);
            data.append('product_id', product_id);
            data.append('user_id', user_id);

            xhr.onloadstart = function () {
                alarndSaveCanvasBtn.classList.add('ml_loading');
                addDisabled();
            };
            xhr.open('POST', url, true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        const responseData = xhr.responseText;
                        console.log(`Response: ${responseData}`);
                        // Assuming responseData is a JSON string containing updated settings
                        try {
                            const updatedSettings = JSON.parse(responseData);
                            settings = updatedSettings;
                            saved_positions = settings.positions ? settings.positions : saved_positions;

                            logoSelector.value = '';
                            const changeEvent = new Event("change", { bubbles: true });
                            logoSelector.dispatchEvent(changeEvent);

                            console.log('Updated Settings:', settings);
                            console.log('saved_positions:', saved_positions);
                        } catch (error) {
                            console.error('Error parsing updated settings:', error);
                        }
                    } else {
                        console.log( 'Error: Unable to send data' );
                    }
                    alarndSaveCanvasBtn.classList.remove('ml_loading');
                    removeDisabled();
                }
            };

            xhr.send(data);
        }
    });

    function clearCanvas() {
        logos = [];
        selectedLogo = null;
        isDragging = false;
        isResizing = false;
        resizeHandle = '';
        draw();
    }

    mergedCanvas.addEventListener('mousedown', (e) => {
        const mouseX = e.offsetX;
        const mouseY = e.offsetY;
        let clickedOnLogo = false;

        for (let i = logos.length - 1; i >= 0; i--) {
            const logo = logos[i];

            const handles = ['top-left', 'top-right', 'bottom-left', 'bottom-right'];
            for (const handle of handles) {
                const x = logo.x + (handle.includes('right') ? logo.width : 0);
                const y = logo.y + (handle.includes('bottom') ? logo.height : 0);
                if (
                    mouseX >= x - 5 &&
                    mouseX <= x + 5 &&
                    mouseY >= y - 5 &&
                    mouseY <= y + 5
                ) {
                    selectedLogo = logo;
                    isResizing = true;
                    resizeHandle = handle;
                    clickedOnLogo = true;
                    break;
                }
            }

            if (!clickedOnLogo && mouseX >= logo.x && mouseX <= logo.x + logo.width &&
                mouseY >= logo.y && mouseY <= logo.y + logo.height) {
                selectedLogo = logo;
                isDragging = true;
                clickedOnLogo = true;
            }
        }

        if (!clickedOnLogo) {
            // Clicked outside of any logo, so deselect
            selectedLogo = null;
        }

        draw();
    });

    mergedCanvas.addEventListener('mousemove', (e) => {
        if (isDragging) {
            selectedLogo.x = e.offsetX - selectedLogo.width / 2;
            selectedLogo.y = e.offsetY - selectedLogo.height / 2;
            draw();
        }
    
        if (isResizing) {
            const mouseX = e.offsetX;
            const mouseY = e.offsetY;
    
            if (resizeHandle.includes('right')) {
                // Store the original dimensions before resizing
                if (!originalLogoDimensions) {
                    originalLogoDimensions = {
                        width: selectedLogo.width,
                        height: selectedLogo.height,
                    };
                }

                console.log("selectedLogo", selectedLogo);
                console.log(originalLogoDimensions);
                const newWidth = mouseX - selectedLogo.x;
    
                // Calculate the new height to maintain the aspect ratio
                const aspectRatio = originalLogoDimensions.width / originalLogoDimensions.height;
                const newHeight = newWidth / aspectRatio;
    
                selectedLogo.width = newWidth;
                selectedLogo.height = newHeight;

                console.log("newWidth", newWidth);
                console.log("newHeight", newHeight);
            }
            if (resizeHandle.includes('bottom')) {
                // Store the original dimensions before resizing
                if (!originalLogoDimensions) {
                    originalLogoDimensions = {
                        width: selectedLogo.width,
                        height: selectedLogo.height,
                    };
                }
    
                const newHeight = mouseY - selectedLogo.y;
    
                // Calculate the new width to maintain the aspect ratio
                const aspectRatio = originalLogoDimensions.width / originalLogoDimensions.height;
                const newWidth = newHeight * aspectRatio;
                
    
                selectedLogo.width = newWidth;
                selectedLogo.height = newHeight;
            }
    
            draw();
        }
    });
    

    mergedCanvas.addEventListener('mouseup', () => {
        isDragging = false;
        isResizing = false;
        resizeHandle = '';
    });
    
    deselectLogoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        selectedLogo = null;
        isDragging = false;
        isResizing = false;
        resizeHandle = '';
        // Optionally, reset rotation angle as well if desired
        rotationInput.value = '0';
        if (selectedLogo) {
            selectedLogo.angle = 0;
        }
        draw();
    });

    addLogoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        
        rotationInput.value = '0';
        
        const selectedLogoPath = logoSelector.value || original_logo_src; // Use the selected logo path or the original path
        const logoImage = new Image();
        logoImage.src = selectedLogoPath;
    
        logoImage.onload = () => {
            // Check if a logo with the same image source already exists
            const existingLogo = logos.find(logo => logo.image.src === logoImage.src);
    
            if (existingLogo) {
                // Logo with the same image source exists
                // You can access its width and height as follows:
                const width = existingLogo.width;
                const height = existingLogo.height;
    
                // Now you have the width and height of the existing logo
                // You can apply these dimensions to the new logo if needed
                // For example, you can set the initial width and height of the new logo:
                const initialWidth = width;
                const initialHeight = height;
    
                // Generate random coordinates for the new logo
                const canvasWidth = mergedCanvas.width;
                const canvasHeight = mergedCanvas.height;
                const randomX = Math.random() * (canvasWidth - initialWidth); // Adjust as needed
                const randomY = Math.random() * (canvasHeight - initialHeight); // Adjust as needed
    
                const newLogo = {
                    x: randomX,
                    y: randomY,
                    width: initialWidth,
                    height: initialHeight,
                    angle: 0,
                    image: logoImage,
                };
    
                logos.push(newLogo);
    
                // Immediately draw the new logo
                draw();
    
                // Select the newly added logo for further interaction
                selectedLogo = newLogo;
            } else {
                // Logo with the same image source doesn't exist
                // Proceed to add the new logo as you did previously
                const initialWidth = logoImage.width;
                const initialHeight = logoImage.height;
                const canvasWidth = mergedCanvas.width;
                const canvasHeight = mergedCanvas.height;
                const randomX = Math.random() * (canvasWidth - initialWidth); // Adjust as needed
                const randomY = Math.random() * (canvasHeight - initialHeight); // Adjust as needed
    
                const newLogo = {
                    x: randomX,
                    y: randomY,
                    width: initialWidth,
                    height: initialHeight,
                    angle: 0,
                    image: logoImage,
                };
    
                logos.push(newLogo);
    
                // Immediately draw the new logo
                draw();
    
                // Select the newly added logo for further interaction
                selectedLogo = newLogo;
            }
        };
    });
    

    removeLogoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (selectedLogo) {
            // Find the index of the selected logo in the logos array
            const index = logos.indexOf(selectedLogo);

            if (index !== -1) {
                // Remove the selected logo from the logos array
                logos.splice(index, 1);
                selectedLogo = null;
                isDragging = false;
                isResizing = false;
                resizeHandle = '';
                draw();
            }
        }
    });

    showInfoBtn.addEventListener('click', (e) => {
        e.preventDefault();
        printLogoInformation();
    });

    // defaultSelectBtn.addEventListener('click', (e) => {
    //     e.preventDefault();
    //     isDefault = !isDefault; // Toggle the value between true and false

    //     // Toggle a CSS class on the defaultSelectBtn
    //     defaultSelectBtn.classList.toggle('button-primary');
    // });
    

    rotationInput.addEventListener('input', (e) => {
        e.preventDefault();

        const newAngle = parseInt(rotationInput.value);
        if (!isNaN(newAngle) && selectedLogo) {
            selectedLogo.angle = (newAngle * Math.PI) / 180;
            draw();
        }
    });

    rotateLeftBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (selectedLogo) {
            selectedLogo.angle -= (15 * Math.PI) / 180; // Rotate 15 degrees to the left
            rotationInput.value = (selectedLogo.angle * 180) / Math.PI;
            draw();
        }
    });

    rotateRightBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (selectedLogo) {
            selectedLogo.angle += (15 * Math.PI) / 180; // Rotate 15 degrees to the right
            rotationInput.value = (selectedLogo.angle * 180) / Math.PI;
            draw();
        }
    });

    function printLogoInformation() {
        console.log('Logo Information:');
        console.log(logos);
        console.log("backgroundImage width", backgroundImage.width);
        console.log("backgroundImage height", backgroundImage.height);
        console.log("mergedCanvas width", mergedCanvas.width);
        console.log("mergedCanvas height", mergedCanvas.height);
        logos.forEach((logo, index) => {
            console.log(`Logo ${index + 1}:`);
            console.log(`Position: X = ${logo.x.toFixed(2)}, Y = ${logo.y.toFixed(2)}`);
            console.log(`Size: Width = ${logo.width.toFixed(2)}, Height = ${logo.height.toFixed(2)}`);
            console.log(`Rotation Angle: ${logo.angle * (180 / Math.PI)} degrees`);
        });
    }

    alarndSaveCanvasBtn.addEventListener('click', (e) => {
        e.preventDefault();
        if (logos) {

            const selectedOption = logoSelector.options[logoSelector.selectedIndex];
            const user_id = selectedOption.getAttribute('data-user_id');
            const product_id = productId.value;
            const logoNumber = logoNumberBtn.value;
            logoType = logoTypesSelect.value;

            // Send the data as a JSON string
            // xhr.send(JSON.stringify(data));

            const xhr = new XMLHttpRequest();
            const url = canvasObj.ajax_url;

            // Create data object with the selected value
            const data = new FormData();
            data.append('action', 'save_canvas');
            data.append('nonce', canvasObj.nonce);
            data.append('logos', JSON.stringify(logos));
            data.append('product_id', product_id);
            data.append('logoNumber', logoNumber);
            data.append('type', logoType);
            data.append('user_id', user_id);

            xhr.onloadstart = function () {
                alarndSaveCanvasBtn.classList.add('ml_loading');
                addDisabled();
            };

            xhr.open('POST', url, true);

            xhr.onreadystatechange = function () {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        const responseData = xhr.responseText;
                        console.log(`Response: ${responseData}`);
                        // Assuming responseData is a JSON string containing updated settings
                        try {
                            const updatedSettings = JSON.parse(responseData);
                            settings = updatedSettings;
                            saved_positions = settings.positions ? settings.positions : saved_positions;
                            console.log('Updated Settings:', settings);
                            console.log('saved_positions:', saved_positions);
                        } catch (error) {
                            console.error('Error parsing updated settings:', error);
                        }
                    } else {
                        console.log( 'Error: Unable to send data' );
                    }
                    alarndSaveCanvasBtn.classList.remove('ml_loading');
                    removeDisabled();
                }
            };

            xhr.send(data);
        }
    });

    draw();
});