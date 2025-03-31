@soge_commerce @ui
Feature: Pay with Soge Commerce during checkout
    In order to buy products
    As a Customer
    I want to be able to pay with Soge Commerce

    Background:
        Given the store operates on a single channel in "United States"
        And there is a user "john@example.com" identified by "password123"
        And the store has a payment method "Soge Commerce" with a code "SOGE_COMMERCE" and Soge Commerce gateway
        And the store has a product "PHP T-Shirt" priced at "$19.99"
        And the store ships everywhere for Free
        And I am logged in as "john@example.com"

    Scenario:
        Given I added product "PHP T-Shirt" to the cart
        And I have proceeded selecting "Soge Commerce" payment method
        When I confirm my order with soge commerce payment
        Then I should see the thank you page
        And I should be notified that my payment is completed
