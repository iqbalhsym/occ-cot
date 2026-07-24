import openpyxl
import json
import os

excel_path = 'database/seeders/Alkes Khusus.xlsx'
if not os.path.exists(excel_path):
    print(f"Error: {excel_path} not found.")
    exit(1)

wb = openpyxl.load_workbook(excel_path)
sheet = wb.active
data = []

# Get headers from first row
headers = [cell.value for cell in sheet[1]]
print("Headers found:", headers)

for row in sheet.iter_rows(min_row=2, values_only=True):
    if any(row):
        row_dict = {}
        for h, val in zip(headers, row):
            if h:
                row_dict[h] = val
        data.append(row_dict)

json_out = 'database/seeders/alkes_khusus.json'
with open(json_out, 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2, ensure_ascii=False)

print(f"Successfully converted Excel to JSON with {len(data)} rows.")
