<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/auth.php';

if (getAuthUserId() <= 0) {
    header('Location: ./login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Trade App PH</title>
    <link rel="stylesheet" href="./assets/css/styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container">
        <header>
            <h1>Trade App PH</h1>
            <p>Track your buy/sell trades and monitor profit in Philippine peso (PHP).</p>
            <div class="header-actions">
                <button type="button" id="logoutButton" class="secondary">Logout</button>
            </div>
        </header>

        <section class="stats-grid">
            <article class="card">
                <h2>Total Buy Cost</h2>
                <p id="buyTotal">₱0.00</p>
            </article>
            <article class="card">
                <h2>Total Sell Value</h2>
                <p id="sellTotal">₱0.00</p>
            </article>
            <article class="card">
                <h2>Net Profit / Loss</h2>
                <p id="netProfit">₱0.00</p>
            </article>
        </section>

        <section class="card">
            <h2 id="formTitle">Add Trade</h2>
            <form id="tradeForm">
                <input type="hidden" id="tradeId">

                <label for="asset">Asset</label>
                <input id="asset" type="text" placeholder="BTC, ETH, Gold, etc." required>

                <label for="type">Type</label>
                <select id="type" required>
                    <option value="buy">Buy</option>
                    <option value="sell">Sell</option>
                </select>

                <label for="quantity">Quantity</label>
                <input id="quantity" type="number" min="0.0001" step="0.0001" required>

                <label for="price">Price (₱ per unit)</label>
                <input id="price" type="number" min="0.01" step="0.01" required>

                <label for="tradeDate">Trade Date</label>
                <input id="tradeDate" type="date" required>

                <div class="form-actions">
                    <button type="submit" id="saveButton">Add Trade</button>
                    <button type="button" id="cancelEditButton" class="secondary hidden">Cancel Edit</button>
                </div>
            </form>
        </section>

        <section class="card">
            <h2>Filters + Export</h2>
            <div class="filter-grid">
                <div>
                    <label for="assetFilter">Asset</label>
                    <input id="assetFilter" type="text" placeholder="Filter by asset">
                </div>
                <div>
                    <label for="searchFilter">Search</label>
                    <input id="searchFilter" type="text" placeholder="Search asset/type">
                </div>
                <div>
                    <label for="fromDateFilter">From Date</label>
                    <input id="fromDateFilter" type="date">
                </div>
                <div>
                    <label for="toDateFilter">To Date</label>
                    <input id="toDateFilter" type="date">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" id="applyFilterButton">Apply Filter</button>
                <button type="button" id="clearFilterButton" class="secondary">Clear Filter</button>
                <button type="button" id="exportCsvButton" class="secondary">Export CSV</button>
            </div>
        </section>

        <section class="card">
            <h2>Realized P/L by Asset</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Sold Qty</th>
                            <th>Open Qty</th>
                            <th>Realized P/L</th>
                        </tr>
                    </thead>
                    <tbody id="realizedTableBody">
                        <tr><td colspan="4">No data.</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="card">
            <h2>Trades</h2>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Asset</th>
                            <th>Type</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="tradeTableBody">
                        <tr><td colspan="8">No trades found.</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="pagination-row">
                <button type="button" id="prevPageButton" class="secondary">Previous</button>
                <span id="paginationInfo">Page 1 of 1</span>
                <button type="button" id="nextPageButton" class="secondary">Next</button>
                <label for="pageSizeSelect">Rows</label>
                <select id="pageSizeSelect">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
        </section>

        <section class="card">
            <h2>Profit Over Time</h2>
            <canvas id="profitChart" height="100"></canvas>
        </section>
    </div>

    <script src="./assets/js/app.js"></script>
</body>
</html>
