$(document).ready(function() {
    loadCategories();
    loadMenuItems();
    AOS.init({
        duration: 1000, // Animation duration
        once: true, // Whether animation should happen only once
    });

    // Smooth scrolling for navigation links
    $('a.nav-link').on('click', function(event) {
        if (this.hash !== "") {
            event.preventDefault();
            var hash = this.hash;

            $('html, body').animate({
                scrollTop: $(hash).offset().top
            }, 800, function() {
                window.location.hash = hash;
            });
        }
    });


   // Navbar background change on scroll
   $(window).scroll(function() {
    if ($(this).scrollTop() > 50) {
        $('.navbar').addClass('navbar-scrolled');
    } else {
        $('.navbar').removeClass('navbar-scrolled');
    }
});

    // AJAX call for logout
    $('.logout-link').on('click', function(e) {
        e.preventDefault(); // Prevent default action of the link
        $.ajax({
            url: 'logout.php', // The PHP script that handles logout
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // If logout is successful, redirect to login page
                    window.location.href = 'login.php';
                } else {
                    alert('Logout failed, please try again.');
                }
            },
            error: function() {
                alert('Error in logout process.');
            }
        });
    });

// Load categories for the filter
function loadCategories() {
    $.ajax({
        url: 'processes/get_categories.php',
        method: 'GET',
        success: function(data) {
            var categoryFilter = $('#category-filter');
            categoryFilter.empty();
            categoryFilter.append($('<option>', { value: '', text: 'All Categories' }));
            $.each(data, function(index, category) {
                categoryFilter.append($('<option>', { value: category.id, text: category.name }));
            });
        },
        error: function() {
            $('#category-filter').append('<option value="">Failed to load categories</option>');
        }
    });
}

// Load menu items based on filters
function loadMenuItems(categoryId = '', sortOption = '') {
    $.ajax({
        url: 'processes/get_menu_items.php',
        method: 'GET',
        data: { category_id: categoryId, sort: sortOption },
        success: function(data) {
            $('#menuContainer').html(data);
            AOS.refresh();
        },
        error: function() {
            $('#menuContainer').html('<p class="text-danger">Failed to load menu items.</p>');
        }
    });
}
// Filter menu items by category
$('#category-filter, #sort-filter').on('change', function() {
    let categoryId = $('#category-filter').val();
    let sortOption = $('#sort-filter').val();
    loadMenuItems(categoryId, sortOption);
});
});


function validateAndAddToCart(menuItemId) {
    let quantity = parseInt($("#quantity-" + menuItemId).val());
    if (!quantity || quantity < 1) {
        alert("Please enter a valid quantity.");
        return;
    }

    $.ajax({
        url: 'processes/add_to_cart.php',
        method: 'POST',
        data: { menu_item_id: menuItemId, quantity: quantity },
        dataType: 'json',
        success: function(response) {
            if (response.error) {
                alert(response.error);
            } else if (response.success) {
                alert(response.success);
            }
        },
        error: function(xhr, status, error) {
            console.error(xhr.responseText);
            alert('Please login to add items to the cart.');
        }
    });
}

