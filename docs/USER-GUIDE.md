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
