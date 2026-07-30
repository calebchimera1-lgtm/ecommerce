-- =====================================================================
-- Demo testimonials for the homepage "Testimonials" section.
-- Optional - run after database/kymera_collection.sql if you want the
-- homepage to show sample customer quotes instead of an empty section.
-- =====================================================================

USE `kymera_collection`;

INSERT INTO `testimonials` (`customer_name`, `rating`, `message`, `is_active`, `sort_order`) VALUES
    ('Amara Whitfield', 5, 'The craftsmanship is extraordinary. Every piece feels like it was made just for me - Kymera has become my go-to for anything special.', 1, 1),
    ('Julian Ferreira', 5, 'From browsing to delivery, the entire experience felt genuinely luxurious. The packaging alone was worth the price.', 1, 2),
    ('Sofia Marchetti', 4, 'Beautiful collection and fast, careful shipping. I have already recommended Kymera to three friends.', 1, 3),
    ('Daniel Okoye', 5, 'I bought a watch as a gift and the recipient has not stopped talking about it. Impeccable quality.', 1, 4);
