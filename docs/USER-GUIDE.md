# Fuel Log user guide

## Record a fill-up

1. Add a vehicle under **Vehicles** with its name, fuel type, and tank capacity. You can also record its type, make, model, year, engine, and registration.
2. Add your fuel station under **Stations**. Include a town or street when you need to distinguish stations with the same name.
3. Choose **New fill-up** and check the vehicle, station, date, odometer, amount added, and price. The date starts at today. The latest fill-up by recorded date supplies defaults such as the vehicle, station, and price. Adjust those defaults to match the receipt.
4. Enter the estimated percentage remaining in the tank **before** adding fuel. Leave it at zero if the tank was empty. Fuel Log multiplies this percentage by the vehicle's tank capacity. The estimate, together with the amount added, gives the estimated fuel after filling.
5. Save the fill-up. Fuel Log calculates the total paid from the volume and price and rounds it to two decimal places.

## Read the dashboard and history

Filter by vehicle and dates, or use **Lifetime**, **Last 30 days**, **Last 90 days**, or **This year**. **Fill-ups** shows the distance since the preceding fill-up for that vehicle. Sort the history by date, distance, efficiency, or amount paid.

For each pair of consecutive fill-ups for the same vehicle, estimated fuel used is the fuel after the earlier fill-up minus the fuel remaining before the later one. Fuel economy divides that estimate by the odometer difference. The first fill-up establishes the baseline. An economy estimate requires a positive distance and positive fuel use.

Intervals belong to the later fill-up, even when the earlier one was outside the date filter. **Average distance between fill-ups** uses valid odometer differences, including zero. **Average time between fill-ups** uses calendar days, so entries on the same date contribute zero days. Average fuel economy divides fuel used across valid intervals by the distance covered in those intervals.

## Review an unusual interval

Fuel Log compares an interval against the vehicle's prior distance-weighted average after at least three valid earlier intervals. It flags consumption more than 1.5 times the prior average. A gap more than 1.7 times the usual time between fill-ups may suggest a missing record.

If the later fill-up was entered with an empty tank, Fuel Log may propose a starting level based on the amount of fuel it expected the vehicle to use. The suggestion applies to the **later fill-up**, at arrival before fuel was added. Use your receipt or recollection to check the estimate before applying it. If a fill-up was missed, add the actual record when you have the details. Review, correct, or dismiss the notice from the app.

## Change display preferences

Open **Preferences** to choose English or Polish, a light or dark theme, or the system theme. You can select metric, US imperial, UK imperial, or custom units. The app tracks one currency per account, initially PLN. Changing the currency code relabels existing amounts without a currency conversion.

## Download fill-ups

On **Fill-ups**, apply the desired vehicle and date filters and choose **Download fill-ups**.

- **CSV** opens in spreadsheets or other tabular tools.
- **TXT** contains tab-separated rows.
- **JSON** contains machine-readable records with the current display units and currency.
- **XLSX** is an Excel workbook that can also be opened in Google Sheets.
- **DOCX** is a Word document. Upload it to Google Drive and open it with Google Docs if you want to edit it there.

## Additional fill-up tools

Preferences can pin a default vehicle and station. With no pinned choice, the latest fill supplies them.

The odometer estimate uses average distance per day between earlier entries for the chosen vehicle. It requires two earlier dates. Review the value before saving. Estimated odometers and tank corrections are marked in history.

Enable fuel economy metering in the vehicle profile to reveal an optional vehicle-reported economy field. The app also calculates consumption from fuel balances.

History rows show Edit and an exclamation mark for an available alert. The exclamation opens a review dialog. Recalculate alerts restores dismissed reviews. Applying an estimate marks the affected entry.

A zero price represents gifted fuel. Its volume and consumption contribute to totals, while average purchase price uses paid fuel only. Fuel prices shows averages, lowest/latest/highest prices, monthly trends and station averages.

Clear filters returns to the full history or dashboard.

## Explore a chart point

Click a dashboard chart point to open its fill-up. You can inspect the date, station, vehicle, price, odometer and tank estimate. Keyboard users can tab to a point and press Enter. Use Cancel to return to history.

## Correct records

Edit is at the right end of each compact history row. Saving changes recalculates the affected intervals, including the next fill-up. Delete opens a confirmation page. An approximately-equal marker identifies algorithm estimates; hover over it for details.

## Understand the figures

Fuel added includes purchases and gifted fuel. Total spent adds receipt amounts. Distance covers journeys between recorded visits. The first entry establishes a baseline. Average distance and time describe the typical gap between visits. With mpg selected, higher economy values mean better efficiency.

If consumption looks wrong, check the date, odometer, purchased amount and tank level first. A missing entry can affect estimates. The exclamation opens the review dialog, where you can apply or dismiss a suggestion.

## Connect other tools

Create an API token in Preferences when a tool needs to read your statistics. Copy it when displayed and keep it private. Replacing or revoking it disconnects tools using that token. The configured Grafana integration uses a separate service credential.
