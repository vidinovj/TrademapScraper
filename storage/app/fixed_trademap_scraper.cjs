// storage/app/fixed_trademap_scraper.cjs
// FIXED VERSION: Handles multi-row headers correctly
// Usage: node fixed_trademap_scraper.cjs <URL>

const puppeteer = require('puppeteer');
const fs = require('fs');

async function scrapeTrademapDataFixed(url) {
    let browser;
    
    try {
        console.error('🚀 Launching browser for FIXED scraping...');
        
        browser = await puppeteer.launch({
            headless: 'new',
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-accelerated-2d-canvas',
                '--disable-gpu',
                '--window-size=1920,1080'
            ]
        });

        const page = await browser.newPage();
        
        await page.setUserAgent('Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        await page.setViewport({ width: 1920, height: 1080 });
        
        page.setDefaultTimeout(60000);
        page.setDefaultNavigationTimeout(60000);

        console.error(`🌐 Navigating to: ${url}`);
        
        await page.goto(url, {
            waitUntil: 'networkidle0',
            timeout: 60000
        });

        console.error('⏳ Waiting for table to load...');
        await page.waitForTimeout(5000);

        // Wait for the specific table we know exists
        try {
            await page.waitForSelector('#ctl00_PageContent_MyGridView1', { timeout: 10000 });
            console.error('✅ Found target table: ctl00_PageContent_MyGridView1');
        } catch (e) {
            console.error('⚠️  Target table not found, proceeding anyway...');
        }

        await page.waitForTimeout(3000);

        console.error('🔍 Extracting data with FIXED multi-row header detection...');

        const tradeData = await page.evaluate(() => {
            
            const extractFullLabel = (cell) => {
                if (!cell) return '';
                
                const title = cell.getAttribute('title');
                if (title && title.trim() && title.trim() !== '&nbsp;' && title.trim() !== '') {
                    return title.trim();
                }

                const links = cell.querySelectorAll('a[title]');
                for (let link of links) {
                    const linkTitle = link.getAttribute('title');
                    if (linkTitle && linkTitle.trim()) {
                        return linkTitle.trim();
                    }
                }

                const textContent = cell.textContent || cell.innerText || '';
                return textContent.trim();
            };

            const extractNumericValue = (text) => {
                if (!text) return 0;
                const cleaned = text.replace(/[^\d.,]/g, '');
                const normalized = cleaned.replace(/,/g, '');
                return parseFloat(normalized) || 0;
            };

            const isValidHsCode = (code) => {
                if (!code) return false;
                const cleaned = code.replace(/[^\d.]/g, '');
                return cleaned.length >= 1 && cleaned.length <= 10 && cleaned !== '00' && cleaned !== '0';
            };

            const cleanText = (text) => {
                if (!text) return '';
                return text.replace(/\s+/g, ' ').replace(/&nbsp;/g, ' ').trim();
            };

            // Target the specific table we know has the data
            const dataTable = document.getElementById('ctl00_PageContent_MyGridView1');
            
            if (!dataTable) {
                console.log('❌ Target data table not found');
                return [];
            }

            console.log('✅ Found target data table');

            const rows = dataTable.querySelectorAll('tr');
            console.log(`📊 Table has ${rows.length} rows`);

            // FIXED: Look for the REAL headers in multiple rows
            let headerRowIndex = -1;
            let headerCells = [];
            
            // Dynamic year calculation (Current Year down to Current Year - 4)
            const currentYear = new Date().getFullYear();
            // Trade data often lags by 1 year, so we check for current year and previous years
            // Adjust this if you want to strictly look for last 5 completed years (e.g., currentYear - 1 down to currentYear - 5)
            // For now, we look for [currentYear, currentYear-1, ..., currentYear-4]
            const targetYears = [];
            for (let y = currentYear; y >= currentYear - 4; y--) {
                targetYears.push(y);
            }
            console.log(`📅 Target years: ${targetYears.join(', ')}`);

            // Check first few rows to find the one with meaningful headers
            for (let i = 0; i < Math.min(3, rows.length); i++) {
                const row = rows[i];
                const cells = row.querySelectorAll('th, td');
                
                console.log(`🔍 Checking row ${i} for headers (${cells.length} cells):`);
                
                let hasYearHeaders = false;
                let hasProductHeaders = false;
                let rowHeaders = [];
                
                for (let j = 0; j < cells.length; j++) {
                    const cellText = cleanText(cells[j].textContent);
                    rowHeaders.push(cellText);
                    console.log(`  Cell ${j}: "${cellText}"`);
                    
                    // Check for year headers (dynamically)
                    for (const year of targetYears) {
                        if (cellText.includes(year.toString())) {
                            hasYearHeaders = true;
                            break;
                        }
                    }
                    if (cellText.includes('Imported value')) {
                        hasYearHeaders = true;
                    }
                    
                    // Check for product headers
                    if (cellText.toLowerCase().includes('product') ||
                        cellText.toLowerCase().includes('code') ||
                        cellText.toLowerCase().includes('hs')) {
                        hasProductHeaders = true;
                    }
                }
                
                console.log(`  Row ${i} - Year headers: ${hasYearHeaders}, Product headers: ${hasProductHeaders}`);
                
                // If this row has both year and product headers, use it
                if (hasYearHeaders && hasProductHeaders) {
                    headerRowIndex = i;
                    headerCells = Array.from(cells);
                    console.log(`✅ Using row ${i} as header row`);
                    break;
                }
            }

            if (headerRowIndex === -1) {
                console.log('❌ No valid header row found');
                return [];
            }

            // Map column indices based on the REAL headers
            const columnMapping = {
                hsCode: -1,
                productLabel: -1
            };
            
            // Initialize dynamic year mappings
            targetYears.forEach(year => {
                columnMapping[`value${year}`] = -1;
            });
            
            console.log('🗺️  Mapping columns from REAL headers:');
            
            for (let i = 0; i < headerCells.length; i++) {
                const headerText = cleanText(headerCells[i].textContent);
                const headerLower = headerText.toLowerCase();
                
                console.log(`  Column ${i}: "${headerText}"`);
                
                // Map year columns dynamically
                targetYears.forEach(year => {
                    if (headerText.includes(year.toString()) || headerLower.includes(year.toString())) {
                        columnMapping[`value${year}`] = i;
                        console.log(`    ✅ MAPPED ${year} column at index ${i}`);
                    }
                });
                
                // Map HS Code column
                if (headerLower.includes('hs') || headerLower.includes('code')) {
                    columnMapping.hsCode = i;
                    console.log(`    ✅ MAPPED HS Code column at index ${i}`);
                }
                
                // Map Product Label column
                if (headerLower.includes('product') || headerLower.includes('label')) {
                    columnMapping.productLabel = i;
                    console.log(`    ✅ MAPPED Product Label column at index ${i}`);
                }
            }

            console.log('🗺️  FINAL COLUMN MAPPING:');
            console.log(JSON.stringify(columnMapping, null, 2));

            // Count successful mappings
            const yearColumnsFound = targetYears.filter(year => 
                columnMapping[`value${year}`] >= 0
            ).length;
            
            console.log(`📅 Year columns mapped: ${yearColumnsFound}/${targetYears.length}`);

            const results = [];
            
            // Process data rows (skip header rows)
            for (let i = headerRowIndex + 1; i < rows.length; i++) {
                const row = rows[i];
                const cells = row.querySelectorAll('td, th');

                if (cells.length < 3) {
                    continue;
                }

                console.log(`\n🔍 PROCESSING DATA ROW ${i} (${cells.length} cells):`);

                // Extract HS Code
                let hsCode = '';
                if (columnMapping.hsCode >= 0 && columnMapping.hsCode < cells.length) {
                    hsCode = cleanText(cells[columnMapping.hsCode].textContent);
                    console.log(`  HS Code from col ${columnMapping.hsCode}: "${hsCode}"`);
                } else {
                    // Fallback: look for HS code in first few columns
                    for (let j = 0; j < Math.min(3, cells.length); j++) {
                        const code = cleanText(cells[j].textContent);
                        if (isValidHsCode(code)) {
                            hsCode = code;
                            console.log(`  HS Code fallback from col ${j}: "${hsCode}"`);
                            break;
                        }
                    }
                }

                // Extract Product Label
                let productLabel = '';
                if (columnMapping.productLabel >= 0 && columnMapping.productLabel < cells.length) {
                    productLabel = extractFullLabel(cells[columnMapping.productLabel]);
                    console.log(`  Product Label from col ${columnMapping.productLabel}: "${productLabel.substring(0, 50)}..."`);
                } else {
                    // Fallback: look for longest meaningful text
                    for (let j = 1; j < cells.length; j++) {
                        const label = extractFullLabel(cells[j]);
                        if (label && label.length > 5 && !isValidHsCode(label)) {
                            productLabel = label;
                            console.log(`  Product Label fallback from col ${j}: "${label.substring(0, 50)}..."`);
                            break;
                        }
                    }
                }

                // Extract values for ALL dynamic years
                const values = {};
                targetYears.forEach(year => {
                    const columnIndex = columnMapping[`value${year}`];
                    if (columnIndex >= 0 && columnIndex < cells.length) {
                        const valueText = cleanText(cells[columnIndex].textContent);
                        values[`value${year}`] = extractNumericValue(valueText);
                        console.log(`  ${year} from col ${columnIndex}: "${valueText}" = ${values[`value${year}`]}`);
                    } else {
                        values[`value${year}`] = 0;
                        console.log(`  ${year}: column not mapped, using 0`);
                    }
                });

                // More lenient validation - accept records with either HS code OR meaningful product label
                const hasHsCode = hsCode && hsCode.length > 0;
                const hasProductLabel = productLabel && productLabel.length > 5;
                const isNotTotal = !productLabel.toLowerCase().includes('total') && !productLabel.toLowerCase().includes('all products');
                
                const isValidRecord = (hasHsCode || hasProductLabel) && isNotTotal;

                if (isValidRecord) {
                    const record = {
                        hsCode: hsCode || 'N/A',
                        productLabel: productLabel,
                        rowIndex: i,
                        ...values // Spread the dynamic year values
                    };

                    results.push(record);
                    console.log(`✅ Added record: ${hsCode || 'N/A'} - ${productLabel.substring(0, 30)}...`);
                } else {
                    console.log(`❌ Skipped record: hsCode="${hsCode}", label="${productLabel}", isTotal=${!isNotTotal}`);
                }
            }

            console.log(`✅ Successfully extracted ${results.length} records`);
            return results;

        });

        console.error(`📈 FIXED scraping results: ${tradeData.length} records`);

        if (tradeData.length === 0) {
            console.error('⚠️  No trade data extracted with FIXED script');
        } else {
            console.error('🎉 SUCCESS! Fixed script extracted data successfully');
            // Log first record for verification
            if (tradeData.length > 0) {
                console.error('📋 First record sample:');
                console.error(`  HS Code: ${tradeData[0].hsCode}`);
                console.error(`  Label: ${tradeData[0].productLabel.substring(0, 50)}...`);
                console.error(`  2020: ${tradeData[0].value2020}`);
                console.error(`  2021: ${tradeData[0].value2021}`);
                console.error(`  2024: ${tradeData[0].value2024}`);
            }
        }

        return tradeData;

    } catch (error) {
        console.error(`❌ Error during fixed scraping: ${error.message}`);
        throw error;
        
    } finally {
        if (browser) {
            console.error('🔒 Closing browser...');
            await browser.close();
        }
    }
}

async function main() {
    const args = process.argv.slice(2);
    
    if (args.length < 1) {
        console.error('❌ Usage: node fixed_trademap_scraper.cjs <URL>');
        process.exit(1);
    }

    const [url] = args;

    try {
        const data = await scrapeTrademapDataFixed(url);
        
        // Write to temporary file
        const tempFile = `/tmp/trademap_fixed_${Date.now()}.json`;
        fs.writeFileSync(tempFile, JSON.stringify(data, null, 2));
        
        // Output filename to stdout
        console.log(tempFile);
        
        console.error(`\n✅ FIXED scraping completed! Found ${data.length} records.`);
        console.error(`📁 Data written to: ${tempFile}`);
        console.error(`🎯 This should now work with your PHP processor!`);
        process.exit(0);

    } catch (error) {
        console.error(`💥 Fixed scraping failed: ${error.message}`);
        process.exit(1);
    }
}

// Handle process signals
process.on('unhandledRejection', (reason, promise) => {
    console.error('💥 Unhandled Rejection:', reason);
    process.exit(1);
});

process.on('SIGINT', () => {
    console.error('🛑 Process interrupted');
    process.exit(1);
});

// Run the fixed scraper
main();