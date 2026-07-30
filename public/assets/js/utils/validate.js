export class ValidationError extends Error {
  constructor(field, expected, actual) {
    super(`Field "${field}" expected ${expected}, got ${JSON.stringify(actual)}`);
    this.name = 'ValidationError';
    this.field = field;
    this.expected = expected;
    this.actual = actual;
  }
}

export function validateShape(data, schema) {
  if (!data || typeof data !== 'object') {
    throw new ValidationError('root', 'object', data);
  }

  for (const [key, rules] of Object.entries(schema)) {
    const value = data[key];

    if (rules.required && (value === undefined || value === null)) {
      throw new ValidationError(key, 'required', value);
    }

    if (value !== undefined && value !== null) {
      const type = rules.type;
      if (type === 'array' && !Array.isArray(value)) {
        throw new ValidationError(key, 'array', value);
      } else if (type === 'object' && (typeof value !== 'object' || Array.isArray(value))) {
        throw new ValidationError(key, 'object', value);
      } else if (type === 'number' && typeof value !== 'number') {
        throw new ValidationError(key, 'number', value);
      } else if (type === 'string' && typeof value !== 'string') {
        throw new ValidationError(key, 'string', value);
      } else if (type === 'boolean' && typeof value !== 'boolean') {
        throw new ValidationError(key, 'boolean', value);
      }
    }
  }

  return true;
}

export const dashboardStatsSchema = {
  period: { type: 'string' },
  executive_kpis: { type: 'object', required: true },
  sales_summary: { type: 'object' },
  purchase_summary: { type: 'object' },
};

export const stockIntelSchema = {
  high_selling: { type: 'array', required: true },
  low_selling: { type: 'array', required: true },
  normal_selling: { type: 'array', required: true },
  old_stock: { type: 'array', required: true },
  inventory_health: { type: 'object', required: true },
  alert_summary: { type: 'object', required: true },
};
