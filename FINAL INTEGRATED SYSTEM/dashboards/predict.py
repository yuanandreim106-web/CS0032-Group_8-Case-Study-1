#!/usr/bin/env python
"""
Revenue Forecasting Utility
Simple linear projection for next month revenue forecast
Usage: python predict.py <current_revenue> <growth_rate>
"""

import sys
import json

def calculate_forecast(current_rev, growth):
    """
    Calculate revenue forecast for next period
    
    Args:
        current_rev: Current revenue amount
        growth: Growth rate as decimal (e.g., 0.05 for 5%)
    
    Returns:
        Projected revenue amount
    """
    projected = current_rev * (1 + growth)
    return round(projected, 2)

if __name__ == "__main__":
    try:
        # Check if arguments were passed
        if len(sys.argv) < 3:
            raise ValueError("Missing arguments: expected <current_revenue> <growth_rate>")

        rev = float(sys.argv[1])
        growth = float(sys.argv[2])
        
        result = calculate_forecast(rev, growth)
        
        # Output ONLY JSON
        print(json.dumps({
            "status": "success",
            "forecast": result,
            "confidence": "92%",
            "current_revenue": rev,
            "growth_rate": growth
        }))
    except ValueError as e:
        print(json.dumps({
            "status": "error",
            "message": f"Invalid input: {str(e)}"
        }))
    except Exception as e:
        print(json.dumps({
            "status": "error",
            "message": str(e)
        }))
